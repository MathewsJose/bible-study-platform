<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Services\EmbeddingGenerationService;
use App\Application\Knowledge\Services\EmbeddingVectorValidator;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;

uses(Tests\TestCase::class);

final class InvalidVectorEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        return [1.0];
    }

    public function embedMany(array $texts): array
    {
        return [[1.0]];
    }

    public function identifier(): string
    {
        return 'invalid-test-model';
    }
}

final class RecordingEmbeddingRepository implements EmbeddingRepositoryInterface
{
    public ?string $failedDocumentId = null;

    public ?string $failureMessage = null;

    public function idsNeedingEmbeddings(?int $limit = null, bool $force = false, bool $retryFailed = false, ?string $documentId = null, ?string $sourceType = null, ?string $sourceName = null): array
    {
        return [];
    }

    public function documentsForEmbedding(array $ids): Collection
    {
        $record = new KnowledgeDocumentRecord();
        $record->id = 'document-1';
        $record->content = 'content';

        return new Collection([$record]);
    }

    public function storeEmbedding(string $documentId, array $embedding, string $provider, string $model, int $dimensions): void {}

    public function markEmbeddingFailed(string $documentId, string $error): void
    {
        $this->failedDocumentId = $documentId;
        $this->failureMessage = $error;
    }

    public function summarizeEmbeddingStatus(array $ids): array
    {
        return ['processed' => 0, 'generated' => 0, 'failures' => 0];
    }

    public function semanticSearch(array $embedding, int $topK, float $threshold, array $filters = []): array
    {
        return [];
    }
}

it('rejects vectors with invalid dimensions before storage', function (): void {
    config()->set('embeddings.dimensions', 3);

    $repository = new RecordingEmbeddingRepository();
    $service = new EmbeddingGenerationService(new InvalidVectorEmbeddingProvider(), $repository, new EmbeddingVectorValidator());

    $result = $service->processBatch(['document-1']);

    expect($result->processed)->toBe(1)
        ->and($result->generated)->toBe(0)
        ->and($result->failures)->toBe(1)
        ->and($repository->failedDocumentId)->toBe('document-1')
        ->and($repository->failureMessage)->toContain('expected 3');
});
