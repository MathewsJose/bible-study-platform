<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Exceptions\InvalidEmbeddingVectorException;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;
use function Pest\Laravel\postJson;

final class SemanticSearchEmbeddingProvider implements EmbeddingProviderInterface
{
    public ?string $embeddedText = null;

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $this->embeddedText = $text;

        return [0.11, 0.22, 0.33];
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    public function identifier(): string
    {
        return 'test-model';
    }
}

final class FailingSemanticSearchEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        throw new RuntimeException('OPENAI_API_KEY is not configured.');
    }

    public function embedMany(array $texts): array
    {
        throw new RuntimeException('OPENAI_API_KEY is not configured.');
    }

    public function identifier(): string
    {
        return 'failing-model';
    }
}

final class EmptySemanticSearchEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return [];
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    public function identifier(): string
    {
        return 'empty-model';
    }
}

final class SemanticSearchRepository implements EmbeddingRepositoryInterface
{
    /** @var list<array{record: KnowledgeDocumentRecord, score: float}> */
    public array $rankedResults = [];

    /** @var list<float>|null */
    public ?array $embedding = null;

    public ?int $limit = null;

    public ?float $threshold = null;

    public ?int $page = null;

    /** @var array<string, mixed> */
    public array $filters = [];

    public bool $throwsInvalidVector = false;

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
        if ($this->throwsInvalidVector) {
            throw new InvalidEmbeddingVectorException('Semantic search requires a non-empty embedding vector.');
        }

        $this->embedding = $embedding;
        $this->limit = $topK;
        $this->threshold = $threshold;
        $this->filters = $filters;

        return array_slice(array_values(array_filter(
            $this->rankedResults,
            static fn (array $result): bool => $result['score'] >= $threshold,
        )), 0, $topK);
    }
}

it('performs semantic search through the API with threshold and top k', function (): void {
    config()->set('embeddings.dimensions', 3);

    $embeddingProvider = new SemanticSearchEmbeddingProvider();
    $repository = new SemanticSearchRepository();

    $repository->rankedResults = [
        [
            'record' => KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']),
            'score' => 0.95,
        ],
        [
            'record' => KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 460']),
            'score' => 0.90,
        ],
        [
            'record' => KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 461']),
            'score' => 0.70,
        ],
    ];

    app()->instance(EmbeddingProviderInterface::class, $embeddingProvider);
    app()->instance(EmbeddingRepositoryInterface::class, $repository);

    postJson('/api/documents/semantic-search', [
        'query' => 'Why did Jesus become man?',
        'top_k' => 1,
        'minimum_score' => 0.8,
    ])
        ->assertOk()
        ->assertJsonPath('data.0.id', $repository->rankedResults[0]['record']->id)
        ->assertJsonPath('data.0.source_type', $repository->rankedResults[0]['record']->source_type)
        ->assertJsonPath('data.0.reference', 'CCC 457')
        ->assertJsonPath('data.0.score', 0.95)
        ->assertJsonPath('data.0.title', $repository->rankedResults[0]['record']->title)
        ->assertJsonPath('data.0.content', $repository->rankedResults[0]['record']->content)
        ->assertJsonPath('meta.top_k', 1)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.minimum_score', 0.8);

    expect($embeddingProvider->embeddedText)->toBe('Why did Jesus become man?')
        ->and($repository->embedding)->toBe([0.11, 0.22, 0.33])
        ->and($repository->limit)->toBe(1)
        ->and($repository->threshold)->toBe(0.8);
});

it('validates semantic search options', function (): void {
    postJson('/api/documents/semantic-search', [
        'query' => 'x',
        'top_k' => 500,
        'score_threshold' => 1.5,
        'minimum_score' => -0.1,
        'tradition' => 'unsupported',
        'page' => 0,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query', 'top_k', 'score_threshold', 'minimum_score', 'tradition', 'page']);
});

it('returns a clear unavailable response when embeddings are not configured', function (): void {
    app()->instance(EmbeddingProviderInterface::class, new FailingSemanticSearchEmbeddingProvider());

    postJson('/api/documents/semantic-search', [
        'query' => 'Why did Jesus become man?',
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Semantic search is unavailable because embeddings are not configured.');
});

it('returns a clear unavailable response when the query embedding is invalid', function (): void {
    config()->set('embeddings.dimensions', 3);
    app()->instance(EmbeddingProviderInterface::class, new EmptySemanticSearchEmbeddingProvider());

    postJson('/api/documents/semantic-search', [
        'query' => 'Why did Jesus become man?',
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Semantic search is unavailable because embeddings are not configured.');
});

it('returns a clear unavailable response when vector search rejects the embedding', function (): void {
    config()->set('embeddings.dimensions', 3);

    $repository = new SemanticSearchRepository();
    $repository->throwsInvalidVector = true;

    app()->instance(EmbeddingProviderInterface::class, new SemanticSearchEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $repository);

    postJson('/api/documents/semantic-search', [
        'query' => 'Why did Jesus become man?',
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Semantic search is unavailable because embeddings are not configured.');
});
