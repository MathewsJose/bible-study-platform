<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Application\Knowledge\Contracts\DocumentImporterInterface;
use App\Application\Knowledge\DTOs\ImportResult;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\ValueObjects\SourceMetadata;

abstract class AbstractDocumentImporter implements DocumentImporterInterface
{
    use TracksImportManifests;

    public function __construct(private readonly KnowledgeDocumentService $documents) {}

    abstract protected function sourceType(): SourceType;

    /**
     * @param  iterable<array<string, mixed>>  $records
     */
    public function import(iterable $records, ?SourceMetadata $sourceMetadata = null): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failures = 0;

        foreach ($records as $record) {
            $record['source_type'] = $this->sourceType()->value;

            if ($sourceMetadata) {
                $record['metadata'] = array_merge($record['metadata'] ?? [], $sourceMetadata->toArray());
            }

            try {
                $status = $this->documents->import($record);
                match ($status) {
                    ImportStatus::Created => $created++,
                    ImportStatus::Updated => $updated++,
                    ImportStatus::Skipped => $skipped++,
                };
            } catch (\Throwable) {
                $failures++;
            }
        }

        return new ImportResult(
            created: $created,
            updated: $updated,
            skipped: $skipped,
            failures: $failures
        );
    }

    public function importFile(string $path, array $metadata = []): ImportResult
    {
        $manifest = $this->startManifest($path, $this->sourceType()->value, basename($path), $metadata);

        try {
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \RuntimeException("Unable to read file: {$path}");
            }

            $segments = preg_split('/\n\s*\n/', trim($content)) ?: [];
            $records = [];
            foreach ($segments as $index => $segment) {
                $records[] = [
                    'source_name' => basename($path),
                    'reference' => basename($path).'#'.($index + 1),
                    'title' => basename($path).'#'.($index + 1),
                    'content' => trim($segment),
                    'tradition' => 'catholic',
                    'metadata' => ['source_file' => basename($path), 'segment' => $index + 1],
                ];
            }

            $result = $this->import($records, $manifest->toSourceMetadata());

            $this->finishManifest($manifest, $result);

            return $result;
        } catch (\Throwable $exception) {
            $this->failManifest($manifest, $exception);
            throw $exception;
        }
    }
}
