<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Domain\Knowledge\ValueObjects\SourceMetadata;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ModernCatechismImporter extends AbstractDocumentImporter
{
    protected function sourceType(): SourceType
    {
        return SourceType::Catechism;
    }

    public function importFile(string $path, array $metadata = []): ImportResult
    {
        if (! str_ends_with($path, '.json')) {
            return parent::importFile($path, $metadata);
        }

        $manifest = $this->startManifest($path, $this->sourceType()->value, 'Catechism of the Catholic Church', $metadata);

        try {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException("Unable to read file: {$path}");
            }

            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            $manifest->update(array_filter([
                'source_url' => $payload['source_url'] ?? null,
                'license' => $payload['license'] ?? null,
                'license_url' => $payload['license_url'] ?? null,
                'language' => $payload['language'] ?? 'en',
            ]));

            $result = $this->importCatechism($payload, $manifest->toSourceMetadata());

            $this->finishManifest($manifest, $result);

            return $result;
        } catch (Throwable $exception) {
            $this->failManifest($manifest, $exception);
            throw $exception;
        }
    }

    private function importCatechism(array $payload, ?SourceMetadata $sourceMetadata): ImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failures = 0;

        $paragraphs = $payload['paragraphs'] ?? [];
        $catechismName = $payload['catechism'] ?? 'Catechism of the Catholic Church';

        foreach ($paragraphs as $p) {
            $pNumber = $p['number'] ?? 0;
            $content = $p['content'] ?? '';
            $title = $p['title'] ?? "CCC {$pNumber}";

            $record = [
                'source_type' => $this->sourceType()->value,
                'source_name' => $catechismName,
                'tradition' => Tradition::Catholic->value,
                'reference' => "CCC {$pNumber}",
                'title' => $title,
                'content' => $content,
                'metadata' => [
                    'catechism' => $catechismName,
                    'paragraph_number' => $pNumber,
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
                Log::error("Modern Catechism import failed: " . $e->getMessage(), [
                    'exception' => $e,
                    'record' => $record,
                ]);
            }
        }

        return new ImportResult($created, $updated, $skipped, $failures);
    }
}
