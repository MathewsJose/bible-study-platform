<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class FeatureEvaluationEmbeddingProvider implements EmbeddingProviderInterface
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
        return 'feature-evaluation-model';
    }
}

final class FeatureEvaluationEmbeddingRepository implements EmbeddingRepositoryInterface
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

it('runs retrieval evaluation through the API and can save results', function (): void {
    config()->set('embeddings.dimensions', 3);

    $expected = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']);
    EvaluationQuestionRecord::factory()->create([
        'question' => 'Why did Jesus become man?',
        'expected_references' => ['CCC 457'],
    ]);

    $repository = new FeatureEvaluationEmbeddingRepository();
    $repository->results = [
        ['record' => $expected, 'score' => 0.95],
    ];

    app()->instance(EmbeddingProviderInterface::class, new FeatureEvaluationEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $repository);

    $this->postJson('/api/evaluations/retrieval', [
        'top_k' => 5,
        'minimum_score' => 0.7,
        'save' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.total_questions', 1)
        ->assertJsonPath('data.hit_rate', 1)
        ->assertJsonPath('data.precision', 1)
        ->assertJsonPath('data.recall', 1)
        ->assertJsonPath('data.mrr', 1);

    expect(DB::table('retrieval_evaluation_runs')->count())->toBe(1)
        ->and(DB::table('retrieval_evaluation_summaries')->count())->toBe(1);
});

it('validates retrieval evaluation API input', function (): void {
    $this->postJson('/api/evaluations/retrieval', [
        'top_k' => 0,
        'minimum_score' => 2,
        'question_id' => 'not-a-uuid',
        'limit' => 0,
        'strategy' => 'invalid',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['top_k', 'minimum_score', 'question_id', 'limit', 'strategy']);
});

it('runs retrieval evaluation from artisan and reports failures', function (): void {
    config()->set('embeddings.dimensions', 3);

    $expected = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']);
    $other = KnowledgeDocumentRecord::factory()->create(['reference' => 'John 1:14']);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Why did Jesus become man?',
        'expected_references' => ['CCC 457'],
    ]);

    $repository = new FeatureEvaluationEmbeddingRepository();
    $repository->results = [
        ['record' => $other, 'score' => 0.9],
    ];

    app()->instance(EmbeddingProviderInterface::class, new FeatureEvaluationEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $repository);

    $status = Artisan::call('evaluate', ['--top-k' => 1]);
    $output = Artisan::output();

    expect($status)->toBe(0)
        ->and($output)->toContain('Retrieval Evaluation')
        ->and($output)->toContain('Hit@1: 0.0%')
        ->and($output)->toContain('Retrieval failures:')
        ->and($output)->toContain('Expected: CCC 457')
        ->and($expected->reference)->toBe('CCC 457');
});

it('compares retrieval strategies from artisan', function (): void {
    config()->set('embeddings.dimensions', 3);

    $expected = KnowledgeDocumentRecord::factory()->create([
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Why did Jesus become man?',
        'expected_references' => ['CCC 457'],
    ]);

    $repository = new FeatureEvaluationEmbeddingRepository();
    $repository->results = [
        ['record' => $expected, 'score' => 0.95],
    ];

    app()->instance(EmbeddingProviderInterface::class, new FeatureEvaluationEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $repository);

    $status = Artisan::call('evaluate:retrieval', ['--top-k' => 1, '--compare' => true]);
    $output = Artisan::output();

    expect($status)->toBe(0)
        ->and($output)->toContain('Retrieval Strategy Comparison')
        ->and($output)->toContain('vector')
        ->and($output)->toContain('lexical')
        ->and($output)->toContain('hybrid');
});

it('runs the hybrid weight grid from artisan', function (): void {
    config()->set('embeddings.dimensions', 3);

    $expected = KnowledgeDocumentRecord::factory()->create([
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Why did Jesus become man?',
        'expected_references' => ['CCC 457'],
    ]);

    $repository = new FeatureEvaluationEmbeddingRepository();
    $repository->results = [
        ['record' => $expected, 'score' => 0.95],
    ];

    app()->instance(EmbeddingProviderInterface::class, new FeatureEvaluationEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $repository);

    $status = Artisan::call('evaluate:retrieval', ['--top-k' => 1, '--weight-grid' => true]);
    $output = Artisan::output();

    expect($status)->toBe(0)
        ->and($output)->toContain('Retrieval Strategy Comparison')
        ->and($output)->toContain('vector 0.8 / lexical 0.2')
        ->and($output)->toContain('vector 0.7 / lexical 0.3')
        ->and($output)->toContain('vector 0.6 / lexical 0.4');
});
