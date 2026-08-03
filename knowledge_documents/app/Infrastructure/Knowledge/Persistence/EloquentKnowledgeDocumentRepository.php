<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentKnowledgeDocumentRepository implements KnowledgeDocumentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()->create($data);
    }

    public function find(string $id): ?KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()->find($id);
    }

    /** @param array<string, mixed> $data */
    public function update(KnowledgeDocumentRecord $record, array $data): KnowledgeDocumentRecord
    {
        $record->fill($data);
        $record->save();

        return $record->refresh();
    }

    public function delete(KnowledgeDocumentRecord $record): void
    {
        $record->delete();
    }

    public function findBySource(string $sourceType, string $sourceName, string $reference): ?KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', $sourceType)
            ->where('source_name', $sourceName)
            ->where('reference', $reference)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, KnowledgeDocumentRecord>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return KnowledgeDocumentRecord::query()
            ->when($filters['source_type'] ?? null, fn (Builder $query, string $value): Builder => $query->where('source_type', $value))
            ->when($filters['tradition'] ?? null, fn (Builder $query, string $value): Builder => $query->where('tradition', $value))
            ->when($filters['reference'] ?? null, fn (Builder $query, string $value): Builder => $query->where('reference', 'like', "%{$value}%"))
            ->latest()
            ->paginate($perPage);
    }

    public function fullTextSearch(string $query, int $limit, array $filters = []): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return array_values($this->applySearchFilters(KnowledgeDocumentRecord::query(), $filters)
                ->where(function (Builder $queryBuilder) use ($query) {
                    $queryBuilder->where('title', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get()
                ->map(fn (KnowledgeDocumentRecord $record): array => ['record' => $record, 'score' => 1.0])
                ->all());
        }

        return array_values($this->applySearchFilters(KnowledgeDocumentRecord::query(), $filters)
            ->select('knowledge_documents.*')
            ->selectRaw("ts_rank(to_tsvector('english', title || ' ' || content || ' ' || reference), plainto_tsquery('english', ?)) as rank", [$query])
            ->whereRaw("to_tsvector('english', title || ' ' || content || ' ' || reference) @@ plainto_tsquery('english', ?)", [$query])
            ->orderByDesc('rank')
            ->limit($limit)
            ->get()
            ->map(fn (KnowledgeDocumentRecord $record): array => ['record' => $record, 'score' => (float) ($record->getAttribute('rank') ?? 0.0)])
            ->all());
    }

    public function semanticSearch(array $embedding, int $limit, float $threshold, int $page, array $filters = []): LengthAwarePaginator
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $this->semanticSearchFallback($embedding, $limit, $threshold, $page, $filters);
        }

        $vectorString = '['.implode(',', $embedding).']';
        $similarityExpression = '1 - (embedding <=> ?::vector)';

        /** @var LengthAwarePaginator<int, KnowledgeDocumentRecord> $results */
        $results = $this->applySearchFilters(KnowledgeDocumentRecord::query(), $filters)
            ->select('knowledge_documents.*')
            ->selectRaw("{$similarityExpression} as similarity", [$vectorString])
            ->whereNotNull('embedding')
            ->whereVectorSimilarTo('embedding', $embedding, $threshold)
            ->paginate($limit, ['*'], 'page', $page);

        /** @var LengthAwarePaginator<int, array{record: KnowledgeDocumentRecord, score: float}> $mapped */
        $mapped = $results->through(
            fn (KnowledgeDocumentRecord $record): array => [
                'record' => $record,
                'score' => (float) ($record->getAttribute('similarity') ?? 0.0),
            ],
        );

        return $mapped;
    }

    private function applySearchFilters(Builder $query, array $filters): Builder
    {
        $driver = DB::getDriverName();

        return $query
            ->when($filters['source_type'] ?? null, fn (Builder $q, string $v) => $q->where('source_type', $v))
            ->when($filters['source_name'] ?? null, fn (Builder $q, string $v) => $q->where('source_name', $v))
            ->when($filters['tradition'] ?? null, fn (Builder $q, string $v) => $q->where('tradition', $v))
            ->when($filters['book'] ?? null, function (Builder $q, string $v) use ($driver) {
                if ($driver === 'pgsql') {
                    return $q->whereRaw("metadata->> 'book' = ?", [$v]);
                }

                return $q->whereRaw("json_extract(metadata, '$.book') = ?", [$v]);
            })
            ->when($filters['chapter'] ?? null, function (Builder $q, $v) use ($driver) {
                $value = is_numeric($v) ? (int) $v : $v;

                if ($driver === 'pgsql') {
                    return $q->whereRaw("metadata->> 'chapter' = ?", [(string) $value]);
                }

                return $q->whereRaw("json_extract(metadata, '$.chapter') = ?", [$value]);
            });
    }

    /**
     * @param  list<float>  $embedding
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{record: KnowledgeDocumentRecord, score: float}>
     */
    private function semanticSearchFallback(array $embedding, int $limit, float $threshold, int $page, array $filters): LengthAwarePaginator
    {
        /** @var Collection<int, array{record: KnowledgeDocumentRecord, score: float}> $ranked */
        $ranked = $this->applySearchFilters(KnowledgeDocumentRecord::query(), $filters)
            ->whereNotNull('embedding')
            ->get()
            ->map(fn (KnowledgeDocumentRecord $record): array => [
                'record' => $record,
                'score' => $this->cosineSimilarity($embedding, $record->embedding ?? []),
            ])
            ->filter(static fn (array $result): bool => $result['score'] >= $threshold)
            ->sortByDesc('score')
            ->values();

        return new LengthAwarePaginator(
            $ranked->forPage($page, $limit)->values()->all(),
            $ranked->count(),
            $limit,
            $page,
        );
    }

    /**
     * @param  list<float>  $queryEmbedding
     * @param  list<float>|string|null  $documentEmbedding
     */
    private function cosineSimilarity(array $queryEmbedding, array|string|null $documentEmbedding): float
    {
        if (is_string($documentEmbedding)) {
            $decoded = json_decode($documentEmbedding, true);
            $documentEmbedding = is_array($decoded) ? $decoded : [];
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
