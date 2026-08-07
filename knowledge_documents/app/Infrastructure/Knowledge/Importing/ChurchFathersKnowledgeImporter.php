<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importing;

use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Support\Str;

final class ChurchFathersKnowledgeImporter extends AbstractFileKnowledgeImporter
{
    /** @var list<string> */
    private const SUPPORTED_AUTHORS = [
        'st. augustine',
        'saint augustine',
        'augustine',
        'st. thomas aquinas',
        'saint thomas aquinas',
        'thomas aquinas',
        'st. john chrysostom',
        'saint john chrysostom',
        'john chrysostom',
        'st. athanasius',
        'saint athanasius',
        'athanasius',
        'st. gregory the great',
        'saint gregory the great',
        'gregory the great',
    ];

    /** @var list<string> */
    private const SCRIPTURE_BOOKS = [
        'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy', 'Joshua', 'Judges', 'Ruth',
        'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans', 'Philippians', 'Ephesians',
        'Corinthians', 'Galatians', 'Hebrews', 'James', 'Peter', 'Jude', 'Revelation',
    ];

    public function identifier(): string
    {
        return 'church_fathers';
    }

    public function displayName(): string
    {
        return 'Church Fathers';
    }

    public function supports(string $path): bool
    {
        $lowerPath = strtolower(basename($path));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['json', 'txt', 'md'], true)) {
            return false;
        }

        if (str_contains($lowerPath, 'church-father') || str_contains($lowerPath, 'church_father') || str_contains($lowerPath, 'fathers')) {
            return true;
        }

        if ($extension !== 'json') {
            return false;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) && isset($payload['sections']) && (isset($payload['author']) || isset($payload['work']));
    }

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult
    {
        if (strtolower(pathinfo($rawDocument->path, PATHINFO_EXTENSION)) !== 'json') {
            return parent::validate($rawDocument);
        }

        try {
            $payload = $rawDocument->jsonPayload();
        } catch (\JsonException $exception) {
            return ValidationResult::invalid(['Church Fathers import JSON is invalid: '.$exception->getMessage()]);
        }

        $errors = [];
        $author = trim((string) ($payload['author'] ?? ''));

        if ($author === '') {
            $errors[] = 'Church Fathers payload is missing author.';
        } elseif (! $this->isSupportedAuthor($author)) {
            $errors[] = "Unsupported Church Father author [{$author}].";
        }

        if (trim((string) ($payload['work'] ?? '')) === '') {
            $errors[] = 'Church Fathers payload is missing work.';
        }

        if (($payload['sections'] ?? []) === [] || ! is_array($payload['sections'] ?? null)) {
            $errors[] = 'Church Fathers payload must contain at least one section.';

            return ValidationResult::invalid($errors);
        }

        $references = [];

        foreach ((array) $payload['sections'] as $index => $section) {
            if (! is_array($section)) {
                $errors[] = "Malformed Church Fathers section at index {$index}.";
                continue;
            }

            $reference = trim((string) ($section['reference'] ?? ''));
            $content = trim((string) ($section['content'] ?? ''));
            $diagnosticReference = $reference !== '' ? $reference : "index {$index}";

            if ($reference === '') {
                $errors[] = "Missing canonical reference for Church Fathers section [{$diagnosticReference}].";
            }

            if (isset($references[$reference])) {
                $errors[] = "Duplicate Church Fathers reference [{$reference}].";
            }
            $references[$reference] = true;

            if ($content === '') {
                $errors[] = "Missing Church Fathers content for [{$diagnosticReference}].";
            }

            if (! $this->validHierarchy($section)) {
                $errors[] = "Invalid Church Fathers hierarchy for [{$diagnosticReference}].";
            }

            foreach ($this->suppliedReferences($section, 'catechism_references') as $catechismReference) {
                if (! preg_match('/^CCC\s+\d+$/', $catechismReference)) {
                    $errors[] = "Invalid Catechism reference [{$catechismReference}] in [{$diagnosticReference}].";
                }
            }
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid(array_values(array_unique($errors)));
    }

    public function normalize(RawKnowledgeDocument $rawDocument): array
    {
        if (strtolower(pathinfo($rawDocument->path, PATHINFO_EXTENSION)) !== 'json') {
            return $this->normalizeTextSegments($rawDocument, SourceType::ChurchFather->value, basename($rawDocument->path));
        }

        $payload = $rawDocument->jsonPayload();
        $author = (string) ($payload['author'] ?? 'Unknown Author');
        $work = (string) ($payload['work'] ?? 'Unknown Work');
        $sourceName = "{$author}, {$work}";
        $language = (string) ($payload['language'] ?? $this->language($rawDocument));
        $documents = [];

        foreach ($this->filteredSections($rawDocument, $payload) as $section) {
            $title = (string) ($section['title'] ?? $section['reference']);
            $reference = (string) $section['reference'];
            $content = trim((string) ($section['content'] ?? ''));
            $checksum = hash('sha256', $sourceName.'|'.$reference.'|'.$content);

            $documents[] = new NormalizedKnowledgeDocument(
                sourceType: SourceType::ChurchFather->value,
                sourceName: $sourceName,
                tradition: Tradition::Catholic->value,
                reference: $reference,
                title: $title,
                content: $content,
                language: $language,
                checksum: $checksum,
                metadata: $this->provenance($rawDocument, [
                    'author' => $author,
                    'author_key' => $this->authorKey($author),
                    'work' => $work,
                    'volume' => $section['volume'] ?? $payload['volume'] ?? null,
                    'section' => $section['section'] ?? $reference,
                    'chapter' => $section['chapter'] ?? null,
                    'paragraph' => $section['paragraph'] ?? null,
                    'language' => $language,
                    'original_language' => $payload['original_language'] ?? null,
                    'translation' => $section['translation'] ?? $payload['translation'] ?? $payload['translator'] ?? null,
                    'century' => $payload['century'] ?? null,
                    'topics' => array_values((array) ($section['topics'] ?? $payload['topics'] ?? [])),
                    'source_edition' => $section['source_edition'] ?? $payload['source_edition'] ?? null,
                    'tradition' => Tradition::Catholic->value,
                    'import_version' => $this->version(),
                    'checksum' => $checksum,
                    'scripture_references' => $this->scriptureReferences($section, $content),
                    'catechism_references' => $this->catechismReferences($section, $content),
                    'church_father_references' => $this->churchFatherReferences($section),
                    'cross_references' => $this->crossReferences($section, $content),
                    'source_url' => $payload['source_url'] ?? null,
                ]),
            );
        }

        return $documents;
    }

    /** @param array<string, mixed> $payload */
    private function filteredSections(RawKnowledgeDocument $rawDocument, array $payload): array
    {
        $authorFilter = isset($rawDocument->metadata['author']) ? $this->authorKey((string) $rawDocument->metadata['author']) : null;
        $payloadAuthorKey = $this->authorKey((string) ($payload['author'] ?? ''));

        if ($authorFilter !== null && $authorFilter !== $payloadAuthorKey) {
            return [];
        }

        return array_values(array_filter(
            (array) ($payload['sections'] ?? []),
            static fn (mixed $section): bool => is_array($section),
        ));
    }

    private function isSupportedAuthor(string $author): bool
    {
        return in_array($this->authorKey($author), array_map(fn (string $supported): string => $this->authorKey($supported), self::SUPPORTED_AUTHORS), true);
    }

    private function authorKey(string $author): string
    {
        return Str::of($author)
            ->lower()
            ->replace(['saint ', 'st. ', 'st '], '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->replace(' ', '_')
            ->toString();
    }

    /** @param array<string, mixed> $section */
    private function validHierarchy(array $section): bool
    {
        return ! (isset($section['paragraph']) && ! isset($section['chapter']) && ! isset($section['section']));
    }

    /** @param array<string, mixed> $section */
    private function scriptureReferences(array $section, string $content): array
    {
        $pattern = '/\b(?:[1-3]\s+)?(?:'.implode('|', self::SCRIPTURE_BOOKS).')\s+\d+:\d+(?:[-–]\d+)?/i';
        preg_match_all($pattern, $content, $matches);

        return array_values(array_unique(array_merge(
            array_map(static fn (string $reference): string => trim($reference), $matches[0] ?? []),
            $this->suppliedReferences($section, 'scripture_references'),
        )));
    }

    /** @param array<string, mixed> $section */
    private function catechismReferences(array $section, string $content): array
    {
        preg_match_all('/\bCCC\s+(\d{1,4})\b/i', $content, $matches);
        $references = array_map(static fn (string $number): string => "CCC {$number}", $matches[1] ?? []);

        return array_values(array_unique(array_merge(
            $references,
            $this->suppliedReferences($section, 'catechism_references'),
            $this->suppliedReferences($section, 'ccc_references'),
        )));
    }

    /** @param array<string, mixed> $section */
    private function churchFatherReferences(array $section): array
    {
        return array_values(array_unique(array_merge(
            $this->suppliedReferences($section, 'church_father_references'),
            $this->suppliedReferences($section, 'patristic_references'),
        )));
    }

    /** @param array<string, mixed> $section */
    private function crossReferences(array $section, string $content): array
    {
        return array_values(array_unique(array_merge(
            $this->scriptureReferences($section, $content),
            $this->catechismReferences($section, $content),
            $this->churchFatherReferences($section),
            $this->suppliedReferences($section, 'cross_references'),
        )));
    }

    /**
     * @param  array<string, mixed>  $section
     * @return list<string>
     */
    private function suppliedReferences(array $section, string $key): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $reference): string => trim((string) $reference),
            (array) ($section[$key] ?? []),
        ), static fn (string $reference): bool => $reference !== ''));
    }
}
