<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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
                        ->orWhere('content', 'like', "%{$query}%")
                        ->orWhere('reference', 'like', "%{$query}%")
                        ->orWhere('source_name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get()
                ->map(fn (KnowledgeDocumentRecord $record): array => ['record' => $record, 'score' => 1.0])
                ->all());
        }

        $likeQuery = "%{$query}%";

        return array_values($this->applySearchFilters(KnowledgeDocumentRecord::query(), $filters)
            ->select('knowledge_documents.*')
            ->selectRaw(
                <<<'SQL'
                (
                    ts_rank_cd(search_vector, websearch_to_tsquery('english', ?))
                    + case when lower(reference) = lower(?) then 10 else 0 end
                    + case when lower(reference) like lower(?) then 5 else 0 end
                    + case when lower(source_name) like lower(?) then 2 else 0 end
                ) as rank
                SQL,
                [$query, $query, $likeQuery, $likeQuery],
            )
            ->where(function (Builder $queryBuilder) use ($query, $likeQuery): void {
                $queryBuilder
                    ->whereRaw("search_vector @@ websearch_to_tsquery('english', ?)", [$query])
                    ->orWhere('reference', 'ilike', $likeQuery)
                    ->orWhere('source_name', 'ilike', $likeQuery);
            })
            ->orderByDesc('rank')
            ->limit($limit)
            ->get()
            ->map(fn (KnowledgeDocumentRecord $record): array => ['record' => $record, 'score' => (float) ($record->getAttribute('rank') ?? 0.0)])
            ->all());
    }

    private function applySearchFilters(Builder $query, array $filters): Builder
    {
        $driver = DB::getDriverName();

        return $query
            ->when($filters['source_type'] ?? null, fn (Builder $q, string $v) => $q->where('source_type', $v))
            ->when($filters['source_types'] ?? null, fn (Builder $q, array $v) => $q->whereIn('source_type', $v))
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
}
