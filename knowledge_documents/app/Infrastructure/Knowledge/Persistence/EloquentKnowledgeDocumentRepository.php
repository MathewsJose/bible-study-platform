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
        return KnowledgeDocumentRecord::query()->create($this->prepareData($data));
    }

    public function find(string $id): ?KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()->find($id);
    }

    /** @param array<string, mixed> $data */
    public function update(KnowledgeDocumentRecord $record, array $data): KnowledgeDocumentRecord
    {
        $record->fill($this->prepareData($data));
        $record->save();

        return $record->refresh();
    }

    public function delete(KnowledgeDocumentRecord $record): void
    {
        $record->delete();
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

    public function fullTextSearch(string $query, int $limit): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return array_values(KnowledgeDocumentRecord::query()
                ->where('title', 'like', "%{$query}%")
                ->orWhere('content', 'like', "%{$query}%")
                ->limit($limit)
                ->get()
                ->map(fn (KnowledgeDocumentRecord $record): array => ['record' => $record, 'score' => 1.0])
                ->all());
        }

        return array_values(KnowledgeDocumentRecord::query()
            ->select('knowledge_documents.*')
            ->selectRaw("ts_rank(to_tsvector('english', title || ' ' || content || ' ' || reference), plainto_tsquery('english', ?)) as rank", [$query])
            ->whereRaw("to_tsvector('english', title || ' ' || content || ' ' || reference) @@ plainto_tsquery('english', ?)", [$query])
            ->orderByDesc('rank')
            ->limit($limit)
            ->get()
            ->map(fn (KnowledgeDocumentRecord $record): array => ['record' => $record, 'score' => (float) ($record->getAttribute('rank') ?? 0.0)])
            ->all());
    }

    public function semanticSearch(array $embedding, int $limit, float $threshold, int $page): LengthAwarePaginator
    {
        if (DB::getDriverName() !== 'pgsql') {
            return new LengthAwarePaginator([], 0, $limit, $page);
        }

        $vector = '['.implode(',', $embedding).']';
        $similarityExpression = '1 - (embedding <=> ?::vector)';

        /** @var LengthAwarePaginator<int, KnowledgeDocumentRecord> $results */
        $results = KnowledgeDocumentRecord::query()
            ->select('knowledge_documents.*')
            ->selectRaw("{$similarityExpression} as similarity", [$vector])
            ->whereNotNull('embedding')
            ->whereRaw("{$similarityExpression} >= ?", [$vector, $threshold])
            ->orderByRaw('embedding <=> ?::vector', [$vector])
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        if (array_key_exists('embedding', $data) && is_array($data['embedding'])) {
            $data['embedding'] = DB::getDriverName() === 'pgsql'
                ? '['.implode(',', $data['embedding']).']'
                : json_encode($data['embedding'], JSON_THROW_ON_ERROR);
        }

        return $data;
    }
}
