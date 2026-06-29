<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Pagination\LengthAwarePaginator;

interface KnowledgeDocumentRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): KnowledgeDocumentRecord;

    public function find(string $id): ?KnowledgeDocumentRecord;

    /** @param array<string, mixed> $data */
    public function update(KnowledgeDocumentRecord $record, array $data): KnowledgeDocumentRecord;

    public function delete(KnowledgeDocumentRecord $record): void;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, KnowledgeDocumentRecord>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return list<array{record: KnowledgeDocumentRecord, score: float}>
     */
    public function fullTextSearch(string $query, int $limit): array;

    /**
     * @param  list<float>  $embedding
     * @return LengthAwarePaginator<int, array{record: KnowledgeDocumentRecord, score: float}>
     */
    public function semanticSearch(array $embedding, int $limit, float $threshold, int $page): LengthAwarePaginator;
}
