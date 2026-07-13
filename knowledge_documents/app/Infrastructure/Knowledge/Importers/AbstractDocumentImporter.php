<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Application\Knowledge\Contracts\DocumentImporterInterface;
use App\Application\Knowledge\DTOs\ImportResult;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Domain\Knowledge\Enums\SourceType;

abstract class AbstractDocumentImporter implements DocumentImporterInterface
{
    use TracksImportManifests;

    public function __construct(private readonly KnowledgeDocumentService $documents) {}

    abstract protected function sourceType(): SourceType;

    public function import(iterable $records): int
    {
        $count = 0;

        foreach ($records as $record) {
            $record['source_type'] = $this->sourceType()->value;
            $this->documents->create($record);
            $count++;
        }

        return $count;
    }

    public function importFile(string $path): ImportResult
    {
        $manifest = $this->startManifest($path, $this->sourceType()->value, basename($path));

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

            $count = $this->import($records);

            $result = new ImportResult(created: $count, skipped: 0, failures: 0);
            $this->finishManifest($manifest, $result);

            return $result;
        } catch (\Throwable $exception) {
            $this->failManifest($manifest, $exception);
            throw $exception;
        }
    }
}
