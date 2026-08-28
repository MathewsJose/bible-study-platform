<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

use function Pest\Laravel\postJson;

final class ScriptureRoutingReadinessAnswerProvider implements LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        return new LLMCompletionResponse(
            content: 'The cited passage is available for study [1].',
            provider: 'sprint-34-test',
            model: $request->model,
            latencyMs: 1,
            promptTokens: 20,
            completionTokens: 8,
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
        return ['provider' => 'sprint-34-test'];
    }

    public function identifier(): string
    {
        return 'sprint-34-test';
    }
}

function createReadinessBibleDocument(string $sourceName, string $reference): KnowledgeDocumentRecord
{
    return KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => $sourceName,
        'tradition' => 'catholic',
        'reference' => $reference,
        'title' => $reference,
        'content' => 'In the beginning was the Word, and the Word was with God, and the Word was God.',
        'metadata' => [
            'book' => 'John',
            'chapter' => 1,
            'verse' => 1,
            'translation' => $sourceName === 'Bible' ? 'legacy' : 'douay_rheims',
        ],
    ]);
}

beforeEach(function (): void {
    config()->set('retrieval.scripture_router.enabled', false);
    config()->set('retrieval.scripture_router.mode', 'hybrid_router');

    config()->set('retrieval.profiles.search.use_vector', false);
    config()->set('retrieval.profiles.search.use_lexical', true);
    config()->set('retrieval.profiles.search.use_expansion', false);
    config()->set('retrieval.profiles.search.graph_depth', 0);

    config()->set('retrieval.profiles.ai_answer.use_vector', false);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.use_expansion', false);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 0);
});

it('keeps the production retrieval path when the feature flag is disabled', function (): void {
    config()->set('retrieval.scripture_router.enabled', false);
    createReadinessBibleDocument('Douay-Rheims Bible', 'John 1:1');

    $result = app(RetrievalEngine::class)->retrieve('John 1:1', 'search', topK: 3, contextLimit: 3);

    expect($result->context)->not->toBeEmpty()
        ->and($result->context[0]->candidate->stages)->not->toContain('scripture_router')
        ->and($result->diagnostics->metrics)->not->toHaveKey('scripture_router_enabled');
});

it('routes exact references through the feature-flagged integration seam', function (): void {
    config()->set('retrieval.scripture_router.enabled', true);
    createReadinessBibleDocument('Bible', 'John 1:1');
    createReadinessBibleDocument('Douay-Rheims Bible', 'John 1:1');

    $result = app(RetrievalEngine::class)->retrieve('John 1:1', 'search', topK: 3, contextLimit: 3);

    expect($result->context[0]->candidate->document->reference)->toBe('John 1:1')
        ->and($result->context[0]->candidate->document->sourceName)->toBe('Douay-Rheims Bible')
        ->and($result->context[0]->candidate->stages)->toContain('scripture_router', 'exact_reference')
        ->and($result->diagnostics->metrics['scripture_router_route'])->toBe('exact_reference');
});

it('falls back to production retrieval when experimental routing configuration fails', function (): void {
    config()->set('retrieval.scripture_router.enabled', true);
    config()->set('retrieval.scripture_router.mode', 'invalid_mode');
    createReadinessBibleDocument('Douay-Rheims Bible', 'John 1:1');

    $result = app(RetrievalEngine::class)->retrieve('John 1:1', 'search', topK: 3, contextLimit: 3);

    expect($result->context)->not->toBeEmpty()
        ->and($result->context[0]->candidate->stages)->not->toContain('scripture_router')
        ->and($result->diagnostics->metrics)->not->toHaveKey('scripture_router_enabled');
});

it('preserves answer api envelope and citations when the router is enabled', function (): void {
    config()->set('retrieval.scripture_router.enabled', true);
    app()->instance(LLMProviderInterface::class, new ScriptureRoutingReadinessAnswerProvider());
    createReadinessBibleDocument('Douay-Rheims Bible', 'John 1:1');

    postJson('/api/answers', ['question' => 'John 1:1'])
        ->assertOk()
        ->assertJsonPath('data.provider', 'sprint-34-test')
        ->assertJsonPath('data.citations.0.reference', 'John 1:1')
        ->assertJsonPath('data.citations.0.source_name', 'Douay-Rheims Bible')
        ->assertJsonStructure([
            'data' => [
                'question',
                'answer',
                'supporting_documents',
                'citations',
                'confidence',
                'metadata',
                'diagnostics',
            ],
        ]);
});

it('keeps explicit legacy source override available through the router adapter', function (): void {
    config()->set('retrieval.scripture_router.enabled', true);
    createReadinessBibleDocument('Bible', 'John 1:1');
    createReadinessBibleDocument('Douay-Rheims Bible', 'John 1:1');

    $result = app(RetrievalEngine::class)->retrieve(
        query: 'John 1:1',
        profile: 'search',
        filters: ['source_name' => 'Bible'],
        topK: 3,
        contextLimit: 3,
    );

    expect($result->context[0]->candidate->document->sourceName)->toBe('Bible')
        ->and($result->context[0]->candidate->document->reference)->toBe('John 1:1');
});
