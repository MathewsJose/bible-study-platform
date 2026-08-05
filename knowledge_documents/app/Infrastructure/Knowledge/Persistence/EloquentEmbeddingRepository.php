<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Exceptions\InvalidEmbeddingVectorException;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentEmbeddingRepository implements EmbeddingRepositoryInterface
{
    public function idsNeedingEmbeddings(
        ?int $limit = null,
        bool $force = false,
        bool $retryFailed = false,
        ?string $documentId = null,
        ?string $sourceType = null,
        ?string $sourceName = null,
    ): array {
        $query = KnowledgeDocumentRecord::query()
            ->select('id')
            ->when($documentId !== null, fn (Builder $query): Builder => $query->whereKey($documentId))
            ->when($sourceType !== null, fn (Builder $query): Builder => $query->where('source_type', $sourceType))
            ->when($sourceName !== null, fn (Builder $query): Builder => $query->where('source_name', $sourceName))
            ->when(! $force && ! $retryFailed, fn (Builder $query): Builder => $query->where('embedding_status', EmbeddingStatus::Pending))
            ->when(! $force && $retryFailed, fn (Builder $query): Builder => $query->whereIn('embedding_status', [
                EmbeddingStatus::Pending,
                EmbeddingStatus::Failed,
            ]))
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
    }

    public function documentsForEmbedding(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection();
        }

        return KnowledgeDocumentRecord::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'content']);
    }

    public function storeEmbedding(string $documentId, array $embedding, string $provider, string $model, int $dimensions): void
    {
        KnowledgeDocumentRecord::query()
            ->whereKey($documentId)
            ->update([
                'embedding' => $this->formatEmbeddingForStorage($embedding),
                'embedding_status' => EmbeddingStatus::Ready,
                'embedding_model' => $model,
                'embedding_provider' => $provider,
                'embedding_dimensions' => $dimensions,
                'embedded_at' => now(),
                'embedding_error' => null,
            ]);
    }

    public function markEmbeddingFailed(string $documentId, string $error): void
    {
        KnowledgeDocumentRecord::query()
            ->whereKey($documentId)
            ->update([
                'embedding_status' => EmbeddingStatus::Failed,
                'embedding_error' => $error,
            ]);
    }

    public function summarizeEmbeddingStatus(array $ids): array
    {
        if ($ids === []) {
            return ['processed' => 0, 'generated' => 0, 'failures' => 0];
        }

        $records = KnowledgeDocumentRecord::query()
            ->whereIn('id', $ids)
            ->get(['embedding_status']);

        return [
            'processed' => count($ids),
            'generated' => $records->where('embedding_status', EmbeddingStatus::Ready)->count(),
            'failures' => $records->where('embedding_status', EmbeddingStatus::Failed)->count(),
        ];
    }

    public function semanticSearch(array $embedding, int $topK, float $threshold, array $filters = []): array
    {
        if ($embedding === []) {
            throw new InvalidEmbeddingVectorException('Semantic search requires a non-empty embedding vector.');
        }

        if (DB::getDriverName() !== 'pgsql') {
            return $this->semanticSearchFallback($embedding, $topK, $threshold, $filters);
        }

        $vectorString = $this->vectorString($embedding);
        $similarityExpression = '1 - (embedding <=> ?::vector)';

        return $this->applySemanticFilters(KnowledgeDocumentRecord::query(), $filters)
            ->select('knowledge_documents.*')
            ->selectRaw("{$similarityExpression} as similarity", [$vectorString])
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $embedding, $threshold)
            ->limit($topK)
            ->get()
            ->map(static fn (KnowledgeDocumentRecord $record): array => [
                'record' => $record,
                'score' => round((float) ($record->getAttribute('similarity') ?? 0.0), 6),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<float>  $embedding
     * @param  array<string, mixed>  $filters
     * @return list<array{record: KnowledgeDocumentRecord, score: float}>
     */
    private function semanticSearchFallback(array $embedding, int $topK, float $threshold, array $filters): array
    {
        return $this->applySemanticFilters(KnowledgeDocumentRecord::query(), $filters)
            ->whereNotNull('embedding')
            ->get()
            ->map(fn (KnowledgeDocumentRecord $record): array => [
                'record' => $record,
                'score' => $this->cosineSimilarity($embedding, $record->embedding ?? []),
            ])
            ->filter(static fn (array $result): bool => $result['score'] >= $threshold)
            ->sortByDesc('score')
            ->take($topK)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySemanticFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['source_types'] ?? null, fn (Builder $query, array $sourceTypes): Builder => $query->whereIn('source_type', $sourceTypes))
            ->when($filters['source_type'] ?? null, fn (Builder $query, string $sourceType): Builder => $query->where('source_type', $sourceType))
            ->when($filters['tradition'] ?? null, fn (Builder $query, string $tradition): Builder => $query->where('tradition', $tradition))
            ->when($filters['source_name'] ?? null, fn (Builder $query, string $sourceName): Builder => $query->where('source_name', $sourceName));
    }

    /**
     * @param  list<float>  $embedding
     */
    private function formatEmbeddingForStorage(array $embedding): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return $this->vectorString($embedding);
        }

        return json_encode($embedding, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<float>  $embedding
     */
    private function vectorString(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }

    /**
     * @param  list<float>  $queryEmbedding
     * @param  list<float>|string|null  $documentEmbedding
     */
    private function cosineSimilarity(array $queryEmbedding, array|string|null $documentEmbedding): float
    {
        if (is_string($documentEmbedding)) {
            $decoded = json_decode($documentEmbedding, true);
            $documentEmbedding = is_array($decoded) ? array_values($decoded) : [];
        }

        $dimensions = min(count($queryEmbedding), count($documentEmbedding));
        if ($dimensions === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $queryMagnitude = 0.0;
        $documentMagnitude = 0.0;

        for ($index = 0; $index < $dimensions; $index++) {
            $queryValue = (float) $queryEmbedding[$index];
            $documentValue = (float) $documentEmbedding[$index];

            $dotProduct += $queryValue * $documentValue;
            $queryMagnitude += $queryValue ** 2;
            $documentMagnitude += $documentValue ** 2;
        }

        if ($queryMagnitude === 0.0 || $documentMagnitude === 0.0) {
            return 0.0;
        }

        return round($dotProduct / (sqrt($queryMagnitude) * sqrt($documentMagnitude)), 6);
    }
}
