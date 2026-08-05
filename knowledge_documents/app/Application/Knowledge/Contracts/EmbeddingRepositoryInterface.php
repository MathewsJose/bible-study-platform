<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;

interface EmbeddingRepositoryInterface
{
    /**
     * @return list<string>
     */
    public function idsNeedingEmbeddings(
        ?int $limit = null,
        bool $force = false,
        bool $retryFailed = false,
        ?string $documentId = null,
        ?string $sourceType = null,
        ?string $sourceName = null,
    ): array;

    /**
     * @param  list<string>  $ids
     * @return Collection<int, KnowledgeDocumentRecord>
     */
    public function documentsForEmbedding(array $ids): Collection;

    /**
     * @param  list<float>  $embedding
     */
    public function storeEmbedding(string $documentId, array $embedding, string $provider, string $model, int $dimensions): void;

    public function markEmbeddingFailed(string $documentId, string $error): void;

    /**
     * @param  list<string>  $ids
     * @return array{processed: int, generated: int, failures: int}
     */
    public function summarizeEmbeddingStatus(array $ids): array;

    /**
     * @param  list<float>  $embedding
     * @param  array<string, mixed>  $filters
     * @return list<array{record: KnowledgeDocumentRecord, score: float}>
     */
    public function semanticSearch(array $embedding, int $topK, float $threshold, array $filters = []): array;
}
