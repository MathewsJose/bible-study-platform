<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Domain\Knowledge\Enums\SourceType;
use App\Application\Knowledge\DTOs\ImportResult;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Domain\Knowledge\Enums\Tradition;
use App\Domain\Knowledge\ValueObjects\SourceMetadata;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class CatechismImporter extends AbstractDocumentImporter
{
    public const SOURCE_NAME = 'Baltimore Catechism';

    protected function sourceType(): SourceType
    {
        return SourceType::Catechism;
    }

    public function importFile(string $path, array $metadata = []): ImportResult
    {
        if (! str_ends_with($path, '.json')) {
            return parent::importFile($path, $metadata);
        }

        $manifest = $this->startManifest($path, $this->sourceType()->value, self::SOURCE_NAME, $metadata);

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

        $lessons = $payload['lessons'] ?? [];
        $catechismName = $payload['catechism'] ?? self::SOURCE_NAME;

        foreach ($lessons as $lesson) {
            $lessonNumber = $lesson['lesson'] ?? 0;
            $lessonTitle = $lesson['title'] ?? "Lesson {$lessonNumber}";

            foreach ($lesson['questions'] ?? [] as $q) {
                $qNumber = $q['number'] ?? 0;
                $questionText = $q['question'] ?? '';
                $answerText = $q['answer'] ?? '';

                $reference = "{$catechismName} Lesson {$lessonNumber}, Question {$qNumber}";
                
                $record = [
                    'source_type' => $this->sourceType()->value,
                    'source_name' => $catechismName,
                    'tradition' => Tradition::Catholic->value,
                    'reference' => $reference,
                    'title' => "Question {$qNumber}: {$questionText}",
                    'content' => "Question: {$questionText}\n\nAnswer: {$answerText}",
                    'metadata' => [
                        'catechism' => $catechismName,
                        'lesson' => $lessonNumber,
                        'lesson_title' => $lessonTitle,
                        'question_number' => $qNumber,
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
                    Log::error("Catechism import failed: " . $e->getMessage(), [
                        'exception' => $e,
                        'record' => $record,
                    ]);
                }
            }
        }

        return new ImportResult($created, $updated, $skipped, $failures);
    }
}
