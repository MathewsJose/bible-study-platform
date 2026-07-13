<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class KnowledgeDocumentService
{
    public function __construct(private KnowledgeDocumentRepositoryInterface $documents) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): KnowledgeDocumentData
    {
        return KnowledgeDocumentData::fromRecord($this->documents->create($data));
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
            $this->documents->create($data);

            return ImportStatus::Created;
        }

        if ($this->isDifferent($existing, $data)) {
            $this->documents->update($existing, $data);

            return ImportStatus::Updated;
        }

        return ImportStatus::Skipped;
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

        return KnowledgeDocumentData::fromRecord($this->documents->update($record, $data));
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
