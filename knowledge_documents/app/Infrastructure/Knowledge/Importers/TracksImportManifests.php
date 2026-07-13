<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Application\Knowledge\DTOs\ImportResult;
use Throwable;

trait TracksImportManifests
{
    protected function startManifest(
        string $path,
        string $sourceType,
        string $sourceName,
        array $extraAttributes = []
    ): ImportManifest
    {
        return ImportManifest::query()->create(array_merge([
            'file_path' => $path,
            'checksum' => hash_file('sha256', $path) ?: '',
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'importer' => class_basename($this),
            'status' => 'running',
            'started_at' => now(),
            'total_records' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
        ], $extraAttributes));
    }

    protected function finishManifest(ImportManifest $manifest, ImportResult $result): void
    {
        $manifest->update([
            'status' => 'completed',
            'finished_at' => now(),
            'total_records' => $result->total(),
            'records_created' => $result->created,
            'records_updated' => $result->updated,
            'records_skipped' => $result->skipped,
            'records_failed' => $result->failures,
        ]);
    }

    protected function failManifest(ImportManifest $manifest, Throwable $exception): void
    {
        $manifest->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);
    }
}
