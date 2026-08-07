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
    /** @var list<string> */
    private const SCRIPTURE_BOOKS = [
        'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth',
        'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans', 'Philippians', 'Ephesians',
        'Corinthians', 'Galatians', 'Hebrews', 'James', 'Peter', 'Jude', 'Revelation',
    ];

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

        if (! isset($payload['paragraphs'])) {
            return ValidationResult::valid();
        }

        $errors = [];
        $paragraphNumbers = [];

        foreach ((array) $payload['paragraphs'] as $index => $paragraph) {
            if (! is_array($paragraph)) {
                $errors[] = "Malformed paragraph metadata at index {$index}.";
                continue;
            }

            $number = (int) ($paragraph['number'] ?? 0);
            $reference = $number > 0 ? "CCC {$number}" : "index {$index}";

            if ($number < 1) {
                $errors[] = "Invalid Catechism paragraph number [{$number}] at index {$index}.";
            }

            if (isset($paragraphNumbers[$number])) {
                $errors[] = "Duplicate Catechism paragraph [CCC {$number}].";
            }
            $paragraphNumbers[$number] = true;

            if (trim((string) ($paragraph['content'] ?? '')) === '') {
                $errors[] = "Missing Catechism content for [{$reference}].";
            }

            if (! $this->validHierarchy($paragraph)) {
                $errors[] = "Broken Catechism hierarchy for [{$reference}].";
            }

            if (isset($paragraph['topics']) && ! is_array($paragraph['topics'])) {
                $errors[] = "Malformed topics metadata for [{$reference}].";
            }

            foreach ($this->suppliedReferences($paragraph, 'ccc_references') as $cccReference) {
                if (! preg_match('/^CCC\s+\d+$/', $cccReference)) {
                    $errors[] = "Invalid Catechism reference [{$cccReference}] in [{$reference}].";
                }
            }
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid(array_values(array_unique($errors)));
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
        $sourceEdition = $this->sourceEdition($rawDocument, $payload);
        $publicationYear = isset($payload['publication_year']) ? (int) $payload['publication_year'] : null;

        foreach ($payload['paragraphs'] as $paragraph) {
            $number = (int) $paragraph['number'];
            $content = trim((string) $paragraph['content']);
            $reference = "CCC {$number}";
            $checksum = hash('sha256', $reference.'|'.$content);
            $hierarchy = $this->hierarchy($paragraph);

            $documents[] = new NormalizedKnowledgeDocument(
                sourceType: SourceType::Catechism->value,
                sourceName: $catechismName,
                tradition: Tradition::Catholic->value,
                reference: $reference,
                title: (string) ($paragraph['title'] ?? $reference),
                content: $content,
                language: $language,
                checksum: $checksum,
                metadata: $this->provenance($rawDocument, [
                    'catechism' => $catechismName,
                    'document_type' => 'catechism_paragraph',
                    'reference_number' => $number,
                    'paragraph_number' => $number,
                    'category' => $paragraph['category'] ?? $hierarchy['part'] ?? null,
                    'topics' => array_values((array) ($paragraph['topics'] ?? [])),
                    'hierarchy' => $hierarchy,
                    'part' => $hierarchy['part'],
                    'section' => $hierarchy['section'],
                    'chapter' => $hierarchy['chapter'],
                    'article' => $hierarchy['article'],
                    'paragraph' => $hierarchy['paragraph'],
                    'language' => $language,
                    'source_edition' => $sourceEdition,
                    'publication_year' => $publicationYear,
                    'tradition' => Tradition::Catholic->value,
                    'internal_references' => $this->cccReferences($paragraph, $content, $reference),
                    'scripture_references' => $this->scriptureReferences($paragraph, $content),
                    'church_father_references' => $this->churchFatherReferences($paragraph),
                    'import_version' => $this->version(),
                    'checksum' => $checksum,
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
                $checksum = hash('sha256', $reference.'|'.$content);

                $documents[] = new NormalizedKnowledgeDocument(
                    sourceType: SourceType::Catechism->value,
                    sourceName: $catechismName,
                    tradition: Tradition::Catholic->value,
                    reference: $reference,
                    title: "Question {$questionNumber}: {$questionText}",
                    content: $content,
                    language: $language,
                    checksum: $checksum,
                    metadata: $this->provenance($rawDocument, [
                        'catechism' => $catechismName,
                        'document_type' => 'catechism_question',
                        'lesson' => $lessonNumber,
                        'lesson_title' => $lessonTitle,
                        'question_number' => $questionNumber,
                        'language' => $language,
                        'tradition' => Tradition::Catholic->value,
                        'checksum' => $checksum,
                    ]),
                );
            }
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $paragraph
     * @return array{part: string|null, section: string|null, chapter: string|null, article: string|null, paragraph: string|null}
     */
    private function hierarchy(array $paragraph): array
    {
        $hierarchy = is_array($paragraph['hierarchy'] ?? null) ? $paragraph['hierarchy'] : [];

        return [
            'part' => isset($paragraph['part']) ? (string) $paragraph['part'] : (isset($hierarchy['part']) ? (string) $hierarchy['part'] : null),
            'section' => isset($paragraph['section']) ? (string) $paragraph['section'] : (isset($hierarchy['section']) ? (string) $hierarchy['section'] : null),
            'chapter' => isset($paragraph['chapter']) ? (string) $paragraph['chapter'] : (isset($hierarchy['chapter']) ? (string) $hierarchy['chapter'] : null),
            'article' => isset($paragraph['article']) ? (string) $paragraph['article'] : (isset($hierarchy['article']) ? (string) $hierarchy['article'] : null),
            'paragraph' => isset($paragraph['paragraph']) ? (string) $paragraph['paragraph'] : (isset($hierarchy['paragraph']) ? (string) $hierarchy['paragraph'] : null),
        ];
    }

    /** @param array<string, mixed> $paragraph */
    private function validHierarchy(array $paragraph): bool
    {
        $hierarchy = $this->hierarchy($paragraph);

        return ! ($hierarchy['section'] !== null && $hierarchy['part'] === null)
            && ! ($hierarchy['chapter'] !== null && $hierarchy['section'] === null)
            && ! ($hierarchy['article'] !== null && $hierarchy['chapter'] === null);
    }

    /** @param array<string, mixed> $paragraph */
    private function cccReferences(array $paragraph, string $content, string $selfReference): array
    {
        preg_match_all('/\bCCC\s+(\d{1,4})\b/i', $content, $matches);
        $references = array_map(static fn (string $number): string => "CCC {$number}", $matches[1] ?? []);

        return array_values(array_filter(array_unique(array_merge(
            $references,
            $this->suppliedReferences($paragraph, 'ccc_references'),
            $this->suppliedReferences($paragraph, 'internal_references'),
        )), static fn (string $reference): bool => $reference !== $selfReference));
    }

    /** @param array<string, mixed> $paragraph */
    private function scriptureReferences(array $paragraph, string $content): array
    {
        $pattern = '/\b(?:[1-3]\s+)?(?:'.implode('|', self::SCRIPTURE_BOOKS).')\s+\d+:\d+(?:[-–]\d+)?(?:[-–]\d+)?/i';
        preg_match_all($pattern, $content, $matches);

        return array_values(array_unique(array_merge(
            array_map(static fn (string $reference): string => trim($reference), $matches[0] ?? []),
            $this->suppliedReferences($paragraph, 'scripture_references'),
        )));
    }

    /** @param array<string, mixed> $paragraph */
    private function churchFatherReferences(array $paragraph): array
    {
        return array_values(array_unique(array_merge(
            $this->suppliedReferences($paragraph, 'church_father_references'),
            $this->suppliedReferences($paragraph, 'patristic_references'),
        )));
    }

    /**
     * @param  array<string, mixed>  $paragraph
     * @return list<string>
     */
    private function suppliedReferences(array $paragraph, string $key): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $reference): string => trim((string) $reference),
            (array) ($paragraph[$key] ?? []),
        ), static fn (string $reference): bool => $reference !== ''));
    }

    /** @param array<string, mixed> $payload */
    private function sourceEdition(RawKnowledgeDocument $rawDocument, array $payload): ?string
    {
        return isset($rawDocument->metadata['source_edition'])
            ? (string) $rawDocument->metadata['source_edition']
            : (isset($payload['source_edition']) ? (string) $payload['source_edition'] : null);
    }
}
