<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Domain\Knowledge\Enums\SourceType;
use App\Application\Knowledge\DTOs\ImportResult;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Domain\Knowledge\Enums\Tradition;
use App\Domain\Knowledge\ValueObjects\SourceMetadata;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ChurchFatherImporter extends AbstractDocumentImporter
{
    protected function sourceType(): SourceType
    {
        return SourceType::ChurchFather;
    }

    public function importFile(string $path, array $metadata = []): ImportResult
    {
        if (! str_ends_with($path, '.json')) {
            return parent::importFile($path, $metadata);
        }

        $manifest = $this->startManifest($path, $this->sourceType()->value, basename($path), $metadata);

        try {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException("Unable to read file: {$path}");
            }

            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $author = $payload['author'] ?? 'Unknown Author';
            $work = $payload['work'] ?? 'Unknown Work';
            $sourceName = "{$author}, {$work}";

            $manifest->update(array_filter([
                'source_name' => $sourceName,
                'source_url' => $payload['source_url'] ?? null,
                'license' => $payload['license'] ?? null,
                'license_url' => $payload['license_url'] ?? null,
                'language' => $payload['language'] ?? 'en',
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            $result = $this->importSections($payload, $manifest->toSourceMetadata());

            $this->finishManifest($manifest, $result);

            return $result;
        } catch (Throwable $exception) {
            $this->failManifest($manifest, $exception);
            throw $exception;
        }
    }

    private function importSections(array $payload, ?SourceMetadata $sourceMetadata): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failures = 0;

        $author = $payload['author'] ?? 'Unknown Author';
        $work = $payload['work'] ?? 'Unknown Work';
        $sourceName = "{$author}, {$work}";

        $sections = $payload['sections'] ?? [];

        foreach ($sections as $section) {
            $title = $section['title'] ?? 'Untitled';
            $reference = $section['reference'] ?? $title;
            $content = $section['content'] ?? '';

            $record = [
                'source_type' => $this->sourceType()->value,
                'source_name' => $sourceName,
                'tradition' => Tradition::Catholic->value,
                'reference' => $reference,
                'title' => $title,
                'content' => $content,
                'metadata' => [
                    'author' => $author,
                    'work' => $work,
                    'section' => $section['section'] ?? $reference,
                    'century' => $payload['century'] ?? null,
                    'original_language' => $payload['original_language'] ?? null,
                    'translator' => $payload['translator'] ?? null,
                ],
            ];

            if ($sourceMetadata) {
                $record['metadata'] = array_merge($record['metadata'], $sourceMetadata->toArray());
            }

            try {
                $status = $this->documents->import($record);
                match ($status) {
                    ImportStatus::Created => $created++,
                    ImportStatus::Updated => $updated++,
                    ImportStatus::Skipped => $skipped++,
                };
            } catch (Throwable $e) {
                $failures++;
                Log::error("Church Father import failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'record' => $record,
                ]);
            }
        }

        return new ImportResult($created, $updated, $skipped, $failures);
    }
}
