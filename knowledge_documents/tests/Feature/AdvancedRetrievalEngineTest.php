<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Retrieval\Services\QueryAnalyzer;
use App\Application\Knowledge\Retrieval\Services\QueryExpansionService;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use App\Domain\Knowledge\Enums\RelationshipType;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRelationshipRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Collection;

use function Pest\Laravel\postJson;

final class AdvancedRetrievalTestEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(static fn (): array => [1.0, 0.0, 0.0], $texts);
    }

    public function identifier(): string
    {
        return 'advanced-retrieval-test';
    }
}

final class AdvancedRetrievalTestEmbeddingRepository implements EmbeddingRepositoryInterface
{
    /** @var list<array{record: KnowledgeDocumentRecord, score: float}> */
    public array $results = [];

    public function idsNeedingEmbeddings(?int $limit = null, bool $force = false, bool $retryFailed = false, ?string $documentId = null, ?string $sourceType = null, ?string $sourceName = null): array
    {
        return [];
    }

    public function documentsForEmbedding(array $ids): Collection
    {
        return new Collection();
    }

    public function storeEmbedding(string $documentId, array $embedding, string $provider, string $model, int $dimensions): void {}

    public function markEmbeddingFailed(string $documentId, string $error): void {}

    public function summarizeEmbeddingStatus(array $ids): array
    {
        return ['processed' => 0, 'generated' => 0, 'failures' => 0];
    }

    public function semanticSearch(array $embedding, int $topK, float $threshold, array $filters = []): array
    {
        return array_slice($this->results, 0, $topK);
    }
}

function advancedRetrievalSource(): string
{
    return 'Sprint 13 Retrieval Test Corpus';
}

beforeEach(function (): void {
    KnowledgeDocumentRecord::query()
        ->where('source_name', advancedRetrievalSource())
        ->delete();
});

it('analyzes mixed theological reference queries', function (): void {
    $query = app(QueryAnalyzer::class)->analyze('Why does CCC 456 mention John 1:14 and Incarnation?');

    expect($query->primaryIntent())->toBe('mixed_query')
        ->and($query->references)->toContain('John 1:14', 'CCC 456')
        ->and($query->topics)->toContain('incarnation')
        ->and($query->isQuestion)->toBeTrue();
});

it('expands theological topics from configured explicit knowledge', function (): void {
    $analyzed = app(QueryAnalyzer::class)->analyze('Incarnation');
    $expansion = app(QueryExpansionService::class)->expand($analyzed);

    expect($expansion->terms)->toContain('Word became flesh')
        ->and($expansion->references)->toContain('John 1:14', 'CCC 456')
        ->and($expansion->explanations[0])->toContain('incarnation');
});

it('builds ranked retrieval context with graph expansion and explanations', function (): void {
    config()->set('embeddings.dimensions', 3);
    config()->set('retrieval.profiles.ai_answer.use_vector', true);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 1);

    $john = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => advancedRetrievalSource(),
        'reference' => 'John 1:14 Advanced',
        'title' => 'The Word became flesh',
        'content' => 'The Word became flesh and dwelt among us.',
        'metadata' => ['topics' => ['incarnation']],
    ]);

    $ccc = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => advancedRetrievalSource(),
        'reference' => 'CCC 456 Advanced',
        'title' => 'Why the Word became flesh',
        'content' => 'The Word became flesh for us in order to save us.',
        'metadata' => ['topics' => ['incarnation']],
    ]);

    KnowledgeDocumentRelationshipRecord::query()->create([
        'source_document_id' => $john->id,
        'target_document_id' => $ccc->id,
        'relationship_type' => RelationshipType::CatechismReference->value,
    ]);

    $embeddingRepository = new AdvancedRetrievalTestEmbeddingRepository();
    $embeddingRepository->results = [
        ['record' => $john, 'score' => 0.95],
    ];

    app()->instance(EmbeddingProviderInterface::class, new AdvancedRetrievalTestEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $embeddingRepository);

    $result = app(RetrievalEngine::class)->retrieve(
        query: 'Incarnation',
        profile: 'ai_answer',
        filters: ['source_name' => advancedRetrievalSource()],
        topK: 5,
        contextLimit: 5,
    );

    $references = array_map(
        static fn ($context): string => $context->candidate->document->reference,
        $result->context,
    );

    expect($references)->toContain('John 1:14 Advanced', 'CCC 456 Advanced')
        ->and($result->diagnostics->metrics['graph_results'])->toBeGreaterThanOrEqual(1)
        ->and($result->context[0]->candidate->scoreBreakdown)->toHaveKey('reranked')
        ->and($result->context[0]->candidate->explanations)->not->toBeEmpty();
});

it('returns structured retrieval context through the api', function (): void {
    config()->set('embeddings.dimensions', 3);
    config()->set('retrieval.profiles.search.use_vector', false);
    config()->set('retrieval.profiles.search.use_lexical', true);

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => advancedRetrievalSource(),
        'reference' => 'CCC 457 Advanced',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
        'metadata' => ['topics' => ['incarnation']],
    ]);

    postJson('/api/retrieval', [
        'query' => 'Word became flesh',
        'profile' => 'search',
        'context_limit' => 3,
        'filters' => [
            'source_name' => advancedRetrievalSource(),
            'source_type' => SourceType::Catechism->value,
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.profile', 'search')
        ->assertJsonPath('data.context.0.reference', 'CCC 457 Advanced')
        ->assertJsonStructure([
            'data' => [
                'query',
                'profile',
                'expansion',
                'context',
                'diagnostics' => ['timings_ms', 'metrics'],
            ],
        ]);
});

it('prints retrieval pipeline diagnostics from artisan', function (): void {
    config()->set('retrieval.profiles.search.use_vector', false);

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => advancedRetrievalSource(),
        'reference' => 'CCC Pipeline Advanced',
        'title' => 'Pipeline result',
        'content' => 'A deterministic retrieval pipeline result for diagnostics.',
    ]);

    $status = Artisan::call('retrieval:pipeline', [
        'query' => 'deterministic retrieval pipeline',
        '--profile' => 'search',
        '--context-limit' => 2,
    ]);
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Advanced Retrieval Pipeline')
        ->and($output)->toContain('Profile: search')
        ->and($output)->toContain('Timings');
});
