<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Answering\Services\AnswerQuestionService;
use App\Application\Knowledge\Answering\Services\CitationBuilder;
use App\Application\Knowledge\Answering\Services\ConfidenceScorer;
use App\Application\Knowledge\Answering\Services\PromptBuilder;
use App\Application\Knowledge\Answering\Services\ResponseValidator;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\postJson;

final class AnswerServiceTestProvider implements LLMProviderInterface
{
    public ?LLMCompletionRequest $lastRequest = null;

    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $this->lastRequest = $request;

        return new LLMCompletionResponse(
            content: 'Jesus became man for our salvation and to reconcile us with God [1].',
            provider: 'test-provider',
            model: $request->model,
            latencyMs: 12,
            promptTokens: 120,
            completionTokens: 16,
        );
    }

    public function stream(LLMCompletionRequest $request): iterable
    {
        yield $this->complete($request)->content;
    }

    public function countTokens(string $text): int
    {
        return str_word_count($text);
    }

    public function metadata(): array
    {
        return ['provider' => 'test-provider'];
    }

    public function identifier(): string
    {
        return 'test-provider';
    }
}

function answerServiceSource(): string
{
    return 'Sprint 14 Answer Test Corpus';
}

beforeEach(function (): void {
    config()->set('ai.provider', 'null');
    config()->set('ai.model', 'test-answer-model');
    config()->set('retrieval.profiles.ai_answer.use_vector', false);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.use_expansion', false);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 0);

    KnowledgeDocumentRecord::query()
        ->where('source_name', answerServiceSource())
        ->delete();
});

it('builds prompts citations confidence and validation warnings deterministically', function (): void {
    $document = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => answerServiceSource(),
        'reference' => 'CCC 457 Answer',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    $retrieval = app(RetrievalEngine::class)->retrieve(
        query: 'CCC 457 Answer',
        profile: 'ai_answer',
        filters: ['source_name' => answerServiceSource()],
    );
    $citations = app(CitationBuilder::class)->build($retrieval);
    $prompt = app(PromptBuilder::class)->build('Why did Jesus become man?', $retrieval, $citations);
    $confidence = app(ConfidenceScorer::class)->score($retrieval, $citations);
    $validation = app(ResponseValidator::class)->validate('Because of salvation [1].', $citations);

    expect($document->exists)->toBeTrue()
        ->and($citations)->toHaveCount(1)
        ->and($citations[0]->reference)->toBe('CCC 457 Answer')
        ->and($prompt->contextBlock)->toContain('[1] CCC 457 Answer')
        ->and($confidence->score)->toBeGreaterThan(0.0)
        ->and($validation->warnings)->toBeEmpty();
});

it('answers questions with structured citations and confidence', function (): void {
    $provider = new AnswerServiceTestProvider();
    app()->instance(LLMProviderInterface::class, $provider);

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => answerServiceSource(),
        'reference' => 'CCC 457 Answer',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    $answer = app(AnswerQuestionService::class)->answer(
        question: 'CCC 457 Answer',
        filters: ['source_name' => answerServiceSource()],
    );

    expect($answer->answer)->toContain('[1]')
        ->and($answer->citations)->toHaveCount(1)
        ->and($answer->confidence->score)->toBeGreaterThan(0.0)
        ->and($answer->provider)->toBe('test-provider')
        ->and($answer->promptTokens)->toBe(120)
        ->and($provider->lastRequest?->messages[0]['role'])->toBe('system');
});

it('returns answer DTOs through the api', function (): void {
    app()->instance(LLMProviderInterface::class, new AnswerServiceTestProvider());

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => answerServiceSource(),
        'reference' => 'John 1:14 Answer',
        'title' => 'The Word became flesh',
        'content' => 'The Word became flesh and dwelt among us.',
    ]);

    postJson('/api/answers', [
        'question' => 'John 1:14 Answer',
        'filters' => ['source_name' => answerServiceSource()],
    ])
        ->assertOk()
        ->assertJsonPath('data.provider', 'test-provider')
        ->assertJsonPath('data.citations.0.reference', 'John 1:14 Answer')
        ->assertJsonStructure([
            'data' => [
                'question',
                'answer',
                'supporting_documents',
                'citations',
                'confidence' => ['score', 'explanations', 'signals'],
                'provider',
                'model',
                'warnings',
                'diagnostics',
            ],
        ]);
});

it('detects missing citations as structured warnings', function (): void {
    $document = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => answerServiceSource(),
        'reference' => 'CCC 456 Answer',
        'title' => 'Why the Word became flesh',
        'content' => 'The Word became flesh for our salvation.',
    ]);

    $retrieval = app(RetrievalEngine::class)->retrieve('CCC 456 Answer', 'ai_answer', ['source_name' => answerServiceSource()]);
    $citations = app(CitationBuilder::class)->build($retrieval);
    $validation = app(ResponseValidator::class)->validate('This answer forgot its citation.', $citations);

    expect($document->exists)->toBeTrue()
        ->and($validation->warnings)->toContain('Answer does not contain bracketed citations.');
});

it('prints answer diagnostics from artisan', function (): void {
    app()->instance(LLMProviderInterface::class, new AnswerServiceTestProvider());

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => answerServiceSource(),
        'reference' => 'CCC CLI Answer',
        'title' => 'CLI answer',
        'content' => 'The Word became flesh for us in order to save us.',
    ]);

    $status = Artisan::call('ai:answer', [
        'question' => 'Why did Jesus become man?',
        '--profile' => 'ai_answer',
    ]);
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('AI Answer')
        ->and($output)->toContain('Confidence:')
        ->and($output)->toContain('Provider:');
});
