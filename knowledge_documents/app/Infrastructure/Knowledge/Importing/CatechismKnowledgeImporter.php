<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importing;

use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;

final class CatechismKnowledgeImporter extends AbstractFileKnowledgeImporter
{
    public function identifier(): string
    {
        return 'catechism';
    }

    public function displayName(): string
    {
        return 'Catechism';
    }

    public function supports(string $path): bool
    {
        $lowerPath = strtolower(basename($path));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['txt', 'md'], true) && str_contains($lowerPath, 'catechism')) {
            return true;
        }

        if ($extension !== 'json') {
            return false;
        }

        if (str_contains($lowerPath, 'catechism') || str_contains($lowerPath, 'ccc')) {
            return true;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) && (isset($payload['paragraphs']) || isset($payload['lessons']));
    }

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult
    {
        if (strtolower(pathinfo($rawDocument->path, PATHINFO_EXTENSION)) !== 'json') {
            return parent::validate($rawDocument);
        }

        try {
            $payload = $rawDocument->jsonPayload();
        } catch (\JsonException $exception) {
            return ValidationResult::invalid(['Catechism import JSON is invalid: '.$exception->getMessage()]);
        }

        if (($payload['paragraphs'] ?? null) === null && ($payload['lessons'] ?? null) === null) {
            return ValidationResult::invalid(['Catechism payload must contain paragraphs or lessons.']);
        }

        return ValidationResult::valid();
    }

    public function normalize(RawKnowledgeDocument $rawDocument): array
    {
        if (strtolower(pathinfo($rawDocument->path, PATHINFO_EXTENSION)) !== 'json') {
            return $this->normalizeTextSegments($rawDocument, SourceType::Catechism->value, basename($rawDocument->path));
        }

        $payload = $rawDocument->jsonPayload();

        if (isset($payload['paragraphs']) && is_array($payload['paragraphs'])) {
            return $this->normalizeParagraphs($rawDocument, $payload);
        }

        return $this->normalizeLessons($rawDocument, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<NormalizedKnowledgeDocument>
     */
    private function normalizeParagraphs(RawKnowledgeDocument $rawDocument, array $payload): array
    {
        $documents = [];
        $catechismName = (string) ($payload['catechism'] ?? 'Catechism of the Catholic Church');
        $language = (string) ($payload['language'] ?? $this->language($rawDocument));

        foreach ($payload['paragraphs'] as $paragraph) {
            $number = (int) $paragraph['number'];
            $content = trim((string) $paragraph['content']);
            $reference = "CCC {$number}";

            $documents[] = new NormalizedKnowledgeDocument(
                sourceType: SourceType::Catechism->value,
                sourceName: $catechismName,
                tradition: Tradition::Catholic->value,
                reference: $reference,
                title: (string) ($paragraph['title'] ?? $reference),
                content: $content,
                language: $language,
                checksum: hash('sha256', $content),
                metadata: $this->provenance($rawDocument, [
                    'catechism' => $catechismName,
                    'paragraph_number' => $number,
                    'source_url' => $payload['source_url'] ?? null,
                ]),
            );
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<NormalizedKnowledgeDocument>
     */
    private function normalizeLessons(RawKnowledgeDocument $rawDocument, array $payload): array
    {
        $documents = [];
        $catechismName = (string) ($payload['catechism'] ?? 'Baltimore Catechism');
        $language = (string) ($payload['language'] ?? $this->language($rawDocument));

        foreach ($payload['lessons'] ?? [] as $lesson) {
            $lessonNumber = (int) ($lesson['lesson'] ?? 0);
            $lessonTitle = (string) ($lesson['title'] ?? "Lesson {$lessonNumber}");

            foreach ($lesson['questions'] ?? [] as $question) {
                $questionNumber = (int) ($question['number'] ?? 0);
                $questionText = (string) ($question['question'] ?? '');
                $answerText = (string) ($question['answer'] ?? '');
                $content = "Question: {$questionText}\n\nAnswer: {$answerText}";
                $reference = "{$catechismName} Lesson {$lessonNumber}, Question {$questionNumber}";

                $documents[] = new NormalizedKnowledgeDocument(
                    sourceType: SourceType::Catechism->value,
                    sourceName: $catechismName,
                    tradition: Tradition::Catholic->value,
                    reference: $reference,
                    title: "Question {$questionNumber}: {$questionText}",
                    content: $content,
                    language: $language,
                    checksum: hash('sha256', $content),
                    metadata: $this->provenance($rawDocument, [
                        'catechism' => $catechismName,
                        'lesson' => $lessonNumber,
                        'lesson_title' => $lessonTitle,
                        'question_number' => $questionNumber,
                    ]),
                );
            }
        }

        return $documents;
    }
}
