<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;

final class DiagnosticsEmbeddingProvider implements EmbeddingProviderInterface
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
        return 'diagnostics-test';
    }
}

final class DiagnosticsEmbeddingRepository implements EmbeddingRepositoryInterface
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

function bindDiagnosticsEmbeddings(KnowledgeDocumentRecord $document): void
{
    config()->set('embeddings.dimensions', 3);
    config()->set('embeddings.model', 'diagnostics-model');

    $repository = new DiagnosticsEmbeddingRepository();
    $repository->results = [
        ['record' => $document, 'score' => 0.95],
    ];

    app()->instance(EmbeddingProviderInterface::class, new DiagnosticsEmbeddingProvider());
    app()->instance(EmbeddingRepositoryInterface::class, $repository);
}

it('diagnoses retrieval results for evaluation questions', function (): void {
    $document = KnowledgeDocumentRecord::factory()->create([
        'source_type' => 'catechism',
        'source_name' => 'Catechism of the Catholic Church',
        'tradition' => 'catholic',
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
        'embedding' => [1.0, 0.0, 0.0],
        'embedding_model' => 'diagnostics-model',
    ]);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Why did Jesus become man?',
        'category' => 'christology',
        'expected_references' => ['CCC 457'],
        'expected_source_types' => ['catechism'],
    ]);

    bindDiagnosticsEmbeddings($document);

    $this->artisan('evaluate:diagnose', ['--top-k' => 1])
        ->expectsOutputToContain('Evaluation Dataset Analysis')
        ->expectsOutputToContain('Question: Why did Jesus become man?')
        ->expectsOutputToContain('Query embedding dimensions: 3 / configured 3')
        ->expectsOutputToContain('VECTOR')
        ->expectsOutputToContain('LEXICAL')
        ->expectsOutputToContain('HYBRID')
        ->expectsOutputToContain('Expected hit: YES')
        ->assertSuccessful();
});

it('reports retrieval health', function (): void {
    $document = KnowledgeDocumentRecord::factory()->create([
        'source_type' => 'catechism',
        'source_name' => 'Catechism of the Catholic Church',
        'tradition' => 'catholic',
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
        'embedding' => [1.0, 0.0, 0.0],
        'embedding_model' => 'diagnostics-model',
    ]);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Why did Jesus become man?',
        'category' => 'christology',
        'expected_references' => ['CCC 457'],
        'expected_source_types' => ['catechism'],
    ]);

    bindDiagnosticsEmbeddings($document);

    $this->artisan('retrieval:health', ['--top-k' => 1])
        ->expectsOutputToContain('Knowledge Documents')
        ->expectsOutputToContain('Embeddings')
        ->expectsOutputToContain('Vector Search')
        ->expectsOutputToContain('Lexical Search')
        ->expectsOutputToContain('Evaluation')
        ->expectsOutputToContain('Potential Problems')
        ->assertSuccessful();
});
