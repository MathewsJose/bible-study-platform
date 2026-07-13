<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class KnowledgeDocumentService
{
    public function __construct(private KnowledgeDocumentRepositoryInterface $documents) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): KnowledgeDocumentData
    {
        return KnowledgeDocumentData::fromRecord($this->documents->create($this->applyEmbeddingStatus($data)));
    }

    /** @param array<string, mixed> $data */
    public function import(array $data): ImportStatus
    {
        $existing = $this->documents->findBySource(
            (string) $data['source_type'],
            (string) $data['source_name'],
            (string) $data['reference']
        );

        if (! $existing) {
            $this->documents->create($this->applyEmbeddingStatus($data));

            return ImportStatus::Created;
        }

        if ($this->isDifferent($existing, $data)) {
            $this->documents->update($existing, $this->applyEmbeddingStatus($data, $existing));

            return ImportStatus::Updated;
        }

        return ImportStatus::Skipped;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyEmbeddingStatus(array $data, ?KnowledgeDocumentRecord $existing = null): array
    {
        if (isset($data['embedding']) && ! empty($data['embedding'])) {
            $data['embedding_status'] = EmbeddingStatus::Ready;
            $data['embedded_at'] = $data['embedded_at'] ?? new DateTimeImmutable();
        } elseif (! isset($data['embedding_status'])) {
            if ($existing === null) {
                $data['embedding_status'] = EmbeddingStatus::Pending;
            } elseif ($this->isDifferent($existing, $data)) {
                $data['embedding_status'] = EmbeddingStatus::Pending;
                $data['embedding'] = null;
                $data['embedded_at'] = null;
            }
        }

        return $data;
    }

    private function isDifferent(KnowledgeDocumentRecord $record, array $data): bool
    {
        $fields = ['title', 'content', 'tradition', 'metadata'];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($record->getAttribute($field) !== $data[$field]) {
                return true;
            }
        }

        return false;
    }

    public function get(string $id): KnowledgeDocumentData
    {
        $record = $this->documents->find($id) ?? throw new NotFoundHttpException('Knowledge document not found.');

        return KnowledgeDocumentData::fromRecord($record);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): KnowledgeDocumentData
    {
        $record = $this->documents->find($id) ?? throw new NotFoundHttpException('Knowledge document not found.');

        return KnowledgeDocumentData::fromRecord($this->documents->update($record, $this->applyEmbeddingStatus($data, $record)));
    }

    public function delete(string $id): void
    {
        $record = $this->documents->find($id) ?? throw new NotFoundHttpException('Knowledge document not found.');

        $this->documents->delete($record);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, KnowledgeDocumentData>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, KnowledgeDocumentData> $mapped */
        $mapped = $this->documents->paginate($filters, $perPage)
            ->through(fn ($record): KnowledgeDocumentData => KnowledgeDocumentData::fromRecord($record));

        return $mapped;
    }
}
