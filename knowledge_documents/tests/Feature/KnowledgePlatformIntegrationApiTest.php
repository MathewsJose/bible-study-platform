<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRelationshipRecord;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

final class PlatformIntegrationAnswerProvider implements LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        return new LLMCompletionResponse(
            content: 'Jesus became man for our salvation [1].',
            provider: 'platform-test-provider',
            model: $request->model,
            latencyMs: 5,
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
        return ['provider' => 'platform-test-provider'];
    }

    public function identifier(): string
    {
        return 'platform-test-provider';
    }
}

function platformIntegrationSource(): string
{
    return 'Sprint 16 Platform Integration Corpus';
}

beforeEach(function (): void {
    config()->set('ai.provider', 'null');
    config()->set('ai.model', 'platform-test-model');
    config()->set('retrieval.profiles.ai_answer.use_vector', false);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.use_expansion', false);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 0);
    config()->set('retrieval.profiles.research.use_vector', false);
    config()->set('retrieval.profiles.research.use_lexical', true);
    config()->set('retrieval.profiles.research.use_expansion', false);
    config()->set('retrieval.profiles.research.graph_depth', 0);

    KnowledgeDocumentRecord::query()
        ->where('source_name', platformIntegrationSource())
        ->delete();
});

it('exposes stable versioned search and reference endpoints', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => platformIntegrationSource(),
        'tradition' => Tradition::Catholic->value,
        'reference' => 'John 1:14 Platform',
        'title' => 'The Word became flesh',
        'content' => 'The Word became flesh and dwelt among us.',
        'metadata' => ['book' => 'John', 'chapter' => 1, 'translation' => 'cpdv'],
    ]);

    getJson('/api/v1/knowledge/search?query=Word became flesh&book=John&chapter=1&limit=5')
        ->assertOk()
        ->assertJsonPath('data.query', 'Word became flesh')
        ->assertJsonPath('data.results.0.reference', 'John 1:14 Platform')
        ->assertJsonPath('meta.total', 1);

    getJson('/api/v1/knowledge/reference/'.rawurlencode('John 1:14 Platform'))
        ->assertOk()
        ->assertJsonPath('data.document.reference', 'John 1:14 Platform');
});

it('exposes related knowledge without raw graph records', function (): void {
    $source = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => platformIntegrationSource(),
        'reference' => 'John 1:14 Related',
    ]);
    $target = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => platformIntegrationSource(),
        'reference' => 'CCC 456 Related',
    ]);

    KnowledgeDocumentRelationshipRecord::query()->create([
        'source_document_id' => $source->id,
        'target_document_id' => $target->id,
        'relationship_type' => 'SCRIPTURE_REFERENCE',
        'confidence' => 1.0,
        'provenance' => [],
        'metadata' => [],
    ]);

    getJson('/api/v1/knowledge/related/'.rawurlencode('John 1:14 Related'))
        ->assertOk()
        ->assertJsonPath('data.document.reference', 'John 1:14 Related')
        ->assertJsonPath('data.relationships.0.document.reference', 'CCC 456 Related')
        ->assertJsonMissingPath('data.relationships.0.source_document_id');
});

it('exposes answer and agent endpoints through integration contracts', function (): void {
    app()->instance(LLMProviderInterface::class, new PlatformIntegrationAnswerProvider());

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => platformIntegrationSource(),
        'reference' => 'CCC 457 Platform Answer',
        'title' => 'Why the Word became Flesh',
        'content' => 'Jesus became man and the Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    postJson('/api/v1/knowledge/answer', [
        'question' => 'Jesus became man',
        'filters' => ['source_name' => platformIntegrationSource()],
    ])
        ->assertOk()
        ->assertJsonPath('data.provider', 'platform-test-provider')
        ->assertJsonPath('data.citations.0.reference', 'CCC 457 Platform Answer');

    postJson('/api/v1/knowledge/agents/run', [
        'input' => 'Jesus became man?',
        'allowed_tools' => ['answer_generation'],
        'filters' => ['source_name' => platformIntegrationSource()],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.tool_results.0.tool', 'answer_generation')
        ->assertJsonMissingPath('data.trace.0.context');
});
