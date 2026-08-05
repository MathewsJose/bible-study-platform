<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Services\LexicalSearchService;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;

final class HybridRetrievalEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (): array => [1.0, 0.0, 0.0], $texts);
    }

    public function identifier(): string
    {
        return 'hybrid-retrieval-test';
    }
}

final class HybridRetrievalEmbeddingRepository implements EmbeddingRepositoryInterface
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

    public function storeEmbedding(string $documentId, array $embedding, string $model): void {}

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

it('performs lexical search with source type and tradition filters', function (): void {
    $matching = KnowledgeDocumentRecord::factory()->create([
        'source_type' => 'catechism',
        'source_name' => 'Catechism of the Catholic Church',
        'tradition' => 'catholic',
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => 'article',
        'tradition' => 'catholic',
        'reference' => 'Article 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us.',
    ]);

    $results = app(LexicalSearchService::class)->search('CCC 457', 5, [
        'source_types' => ['catechism'],
        'tradition' => 'catholic',
    ]);

    expect($results)->toHaveCount(1)
        ->and($results[0]->document->id)->toBe($matching->id)
        ->and($results[0]->score)->toBeGreaterThan(0.0);
});

it('returns flat ranked hybrid search results through the API', function (): void {
    config()->set('embeddings.dimensions', 3);
    config()->set('retrieval.hybrid.vector_weight', 0.7);
    config()->set('retrieval.hybrid.lexical_weight', 0.3);

    $ccc457 = KnowledgeDocumentRecord::factory()->create([
        'source_type' => 'catechism',
        'tradition' => 'catholic',
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    $repository = new HybridRetrievalEmbeddingRepository();
    $repository->results = [
        ['record' => $ccc457, 'score' => 0.95],
    ];

    app()->instance(EmbeddingProviderInterface::class, new HybridRetrievalEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $repository);

    $this->postJson('/api/documents/hybrid-search', [
        'query' => 'Why did Jesus become man?',
        'top_k' => 10,
        'minimum_score' => 0.1,
        'source_types' => ['catechism', 'bible_verse'],
        'tradition' => 'catholic',
    ])
        ->assertOk()
        ->assertJsonPath('data.0.reference', 'CCC 457')
        ->assertJsonPath('data.0.vector_score', 1)
        ->assertJsonPath('data.0.lexical_score', 0)
        ->assertJsonPath('data.0.combined_score', 0.7)
        ->assertJsonPath('meta.top_k', 10)
        ->assertJsonPath('meta.vector_weight', 0.7)
        ->assertJsonPath('meta.lexical_weight', 0.3);
});
