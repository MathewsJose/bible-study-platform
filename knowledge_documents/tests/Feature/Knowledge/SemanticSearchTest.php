<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Pagination\LengthAwarePaginator;
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
}

final class SemanticSearchRepository implements KnowledgeDocumentRepositoryInterface
{
    /** @var list<array{record: KnowledgeDocumentRecord, score: float}> */
    public array $rankedResults = [];

    /** @var list<float>|null */
    public ?array $embedding = null;

    public ?int $limit = null;

    public ?float $threshold = null;

    public ?int $page = null;

    public function create(array $data): KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()->create($data);
    }

    public function find(string $id): ?KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()->find($id);
    }

    public function update(KnowledgeDocumentRecord $record, array $data): KnowledgeDocumentRecord
    {
        $record->fill($data)->save();

        return $record->refresh();
    }

    public function delete(KnowledgeDocumentRecord $record): void
    {
        $record->delete();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage);
    }

    public function fullTextSearch(string $query, int $limit): array
    {
        return [];
    }

    public function semanticSearch(array $embedding, int $limit, float $threshold, int $page): LengthAwarePaginator
    {
        $this->embedding = $embedding;
        $this->limit = $limit;
        $this->threshold = $threshold;
        $this->page = $page;

        $filtered = array_values(array_filter(
            $this->rankedResults,
            static fn (array $result): bool => $result['score'] >= $threshold,
        ));
        $pageItems = array_slice($filtered, ($page - 1) * $limit, $limit);

        return new LengthAwarePaginator($pageItems, count($filtered), $limit, $page);
    }
}

it('performs semantic search through the API with threshold and pagination', function (): void {
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
    app()->instance(KnowledgeDocumentRepositoryInterface::class, $repository);

    postJson('/api/documents/semantic-search', [
        'query' => 'Why did Jesus become man?',
        'limit' => 1,
        'page' => 2,
        'score_threshold' => 0.8,
    ])
        ->assertOk()
        ->assertJsonPath('results.0.reference', 'CCC 460')
        ->assertJsonPath('results.0.score', 0.90)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.limit', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.score_threshold', 0.8);

    expect($embeddingProvider->embeddedText)->toBe('Why did Jesus become man?')
        ->and($repository->embedding)->toBe([0.11, 0.22, 0.33])
        ->and($repository->limit)->toBe(1)
        ->and($repository->page)->toBe(2)
        ->and($repository->threshold)->toBe(0.8);
});

it('validates semantic search options', function (): void {
    postJson('/api/documents/semantic-search', [
        'query' => 'x',
        'limit' => 500,
        'score_threshold' => 1.5,
        'page' => 0,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['query', 'limit', 'score_threshold', 'page']);
});

it('returns a clear unavailable response when embeddings are not configured', function (): void {
    app()->instance(EmbeddingProviderInterface::class, new FailingSemanticSearchEmbeddingProvider());

    postJson('/api/documents/semantic-search', [
        'query' => 'Why did Jesus become man?',
    ])
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Semantic search is unavailable because embeddings are not configured.');
});
