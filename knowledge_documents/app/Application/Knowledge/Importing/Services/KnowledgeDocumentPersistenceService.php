<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Services;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Events\Knowledge\DocumentImported;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class KnowledgeDocumentPersistenceService
{
    public function __construct(private KnowledgeDocumentService $documents) {}

    /**
     * @param  list<NormalizedKnowledgeDocument>  $documents
     * @return array{result: ImportResult, changed_document_ids: list<string>, errors: list<string>}
     */
    public function persist(array $documents): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failures = 0;
        $changedDocumentIds = [];
        $errors = [];

        foreach ($documents as $document) {
            try {
                $status = $this->documents->import($document->toPersistencePayload());

                match ($status) {
                    ImportStatus::Created => $created++,
                    ImportStatus::Updated => $updated++,
                    ImportStatus::Skipped => $skipped++,
                };

                if ($status !== ImportStatus::Skipped) {
                    $record = KnowledgeDocumentRecord::query()
                        ->where('source_type', $document->sourceType)
                        ->where('source_name', $document->sourceName)
                        ->where('reference', $document->reference)
                        ->first();

                    if ($record instanceof KnowledgeDocumentRecord) {
                        $changedDocumentIds[] = $record->id;
                        DocumentImported::dispatch($record->id);
                    }
                }
            } catch (\Throwable $exception) {
                $failures++;
                $errors[] = implode(' | ', [
                    "source_type={$document->sourceType}",
                    "source_name={$document->sourceName}",
                    "reference={$document->reference}",
                    'category=persistence_failure',
                    'error='.$exception->getMessage(),
                ]);
            }
        }

        return [
            'result' => new ImportResult($created, $updated, $skipped, $failures),
            'changed_document_ids' => array_values(array_unique($changedDocumentIds)),
            'errors' => $errors,
        ];
    }
}
