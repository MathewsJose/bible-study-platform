<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importing;

use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Support\Str;

final class BibleKnowledgeImporter extends AbstractFileKnowledgeImporter
{
    public function __construct(private readonly BibleCanon $canon) {}

    public function identifier(): string
    {
        return 'bible';
    }

    public function displayName(): string
    {
        return 'Bible';
    }

    public function supports(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
            return false;
        }

        $lowerPath = strtolower(basename($path));

        if (str_contains($lowerPath, 'bible') || str_contains($lowerPath, 'douay')) {
            return true;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload)
            && (
                isset($payload['book'], $payload['chapter'], $payload['verses'])
                || isset($payload['book'], $payload['chapters'])
                || isset($payload['books'])
            );
    }

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult
    {
        try {
            $payload = $rawDocument->jsonPayload();
            $chapters = $this->chapters($rawDocument);
        } catch (\JsonException $exception) {
            return ValidationResult::invalid(['Bible import JSON is invalid: '.$exception->getMessage()]);
        }

        if (! isset($payload['books']) && ! isset($payload['book'], $payload['chapter'], $payload['verses']) && ! isset($payload['book'], $payload['chapters'])) {
            $errors = [];

            if (! array_key_exists('book', $payload)) {
                $errors[] = 'The book field is required.';
            }

            if (! array_key_exists('chapter', $payload)) {
                $errors[] = 'The chapter field is required.';
            }

            if (! array_key_exists('verses', $payload)) {
                $errors[] = 'The verses field is required.';
            }

            return ValidationResult::invalid($errors === [] ? ['Bible payload must contain books, book chapters, or a chapter.'] : $errors);
        }

        if ($chapters === []) {
            return ValidationResult::invalid(['Bible payload must contain at least one chapter with verses.']);
        }

        $errors = [];
        $references = [];
        $lastCanonicalOrder = 0;

        foreach ($chapters as $chapter) {
            $validBook = $this->canon->isValidBook($chapter['book']);
            if (! $validBook) {
                $errors[] = "Invalid Bible book [{$chapter['book']}].";
            }

            if ($chapter['chapter'] < 1) {
                $errors[] = "Invalid chapter number [{$chapter['chapter']}] for {$chapter['book']}.";
            }

            $previousVerse = 0;

            foreach ($chapter['verses'] as $verse) {
                $reference = "{$chapter['book']} {$chapter['chapter']}:{$verse['verse']}";

                if ($verse['verse'] < 1) {
                    $errors[] = "Invalid verse number [{$verse['verse']}] for {$chapter['book']} {$chapter['chapter']}.";
                }

                if ($verse['verse'] <= $previousVerse) {
                    $errors[] = "Broken verse ordering near [{$reference}].";
                }

                if (trim($verse['text']) === '') {
                    $errors[] = "Missing verse content for [{$reference}].";
                }

                if (isset($references[$reference])) {
                    $errors[] = "Duplicate Bible reference [{$reference}].";
                }

                $references[$reference] = true;
                $previousVerse = $verse['verse'];
                $canonicalOrder = $validBook
                    ? $this->canon->canonicalOrder($chapter['book'], $chapter['chapter'], $verse['verse'])
                    : $lastCanonicalOrder + 1;

                if ($validBook && $canonicalOrder <= $lastCanonicalOrder) {
                    $errors[] = "Broken canonical ordering near [{$reference}].";
                }

                $lastCanonicalOrder = $canonicalOrder;
            }
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid(array_values(array_unique($errors)));
    }

    public function normalize(RawKnowledgeDocument $rawDocument): array
    {
        $documents = [];
        $chapters = $this->filteredChapters($rawDocument);
        $translation = $this->translation($rawDocument);
        $sourceName = $this->sourceName($translation);
        $language = $this->payloadLanguage($rawDocument);
        $sourceEdition = $this->sourceEdition($rawDocument);

        foreach ($chapters as $chapter) {
            $book = $this->canon->canonicalBook($chapter['book']);
            $bookAbbreviation = $this->canon->abbreviation($book, $chapter['book_abbreviation']);
            $testament = $this->canon->testament($book, $chapter['testament']);
            $orderedVerses = [];

            foreach ($chapter['verses'] as $verse) {
                $content = trim($verse['text']);
                $reference = "{$book} {$chapter['chapter']}:{$verse['verse']}";
                $verseChecksum = hash('sha256', $translation.'|'.$reference.'|'.$content);
                $orderedVerses[] = [
                    'verse' => $verse['verse'],
                    'reference' => $reference,
                    'text' => $content,
                    'cross_references' => $verse['cross_references'],
                ];

                $documents[] = new NormalizedKnowledgeDocument(
                    sourceType: SourceType::BibleVerse->value,
                    sourceName: $sourceName,
                    tradition: Tradition::Catholic->value,
                    reference: $reference,
                    title: $reference,
                    content: $content,
                    language: $language,
                    checksum: $verseChecksum,
                    metadata: $this->provenance($rawDocument, [
                        'book' => $book,
                        'book_abbreviation' => $bookAbbreviation,
                        'chapter' => $chapter['chapter'],
                        'verse' => $verse['verse'],
                        'testament' => $testament,
                        'translation' => $translation,
                        'tradition' => Tradition::Catholic->value,
                        'canonical_book_order' => $this->canon->bookOrder($book),
                        'canonical_order' => $this->canon->canonicalOrder($book, $chapter['chapter'], $verse['verse']),
                        'source_edition' => $sourceEdition,
                        'import_version' => $this->version(),
                        'checksum' => $verseChecksum,
                        'cross_references' => $verse['cross_references'],
                        'canon' => 'catholic',
                    ]),
                );
            }

            $chapterContent = implode(' ', array_map(
                static fn (array $verse): string => "[{$verse['verse']}] {$verse['text']}",
                $orderedVerses,
            ));
            $chapterReference = "{$book} {$chapter['chapter']}";
            $chapterChecksum = hash('sha256', $translation.'|'.$chapterReference.'|'.$chapterContent);

            $documents[] = new NormalizedKnowledgeDocument(
                sourceType: SourceType::BibleChapter->value,
                sourceName: $sourceName,
                tradition: Tradition::Catholic->value,
                reference: $chapterReference,
                title: "{$book} Chapter {$chapter['chapter']}",
                content: $chapterContent,
                language: $language,
                checksum: $chapterChecksum,
                metadata: $this->provenance($rawDocument, [
                    'book' => $book,
                    'book_abbreviation' => $bookAbbreviation,
                    'chapter' => $chapter['chapter'],
                    'testament' => $testament,
                    'translation' => $translation,
                    'tradition' => Tradition::Catholic->value,
                    'canonical_book_order' => $this->canon->bookOrder($book),
                    'canonical_order' => $this->canon->canonicalOrder($book, $chapter['chapter']),
                    'source_edition' => $sourceEdition,
                    'import_version' => $this->version(),
                    'checksum' => $chapterChecksum,
                    'verse_count' => count($orderedVerses),
                    'verses' => $orderedVerses,
                    'cross_references' => $this->chapterCrossReferences($orderedVerses),
                    'canon' => 'catholic',
                ]),
            );
        }

        return $documents;
    }

    /**
     * @return list<array{
     *     book: string,
     *     book_abbreviation: string|null,
     *     testament: string|null,
     *     chapter: int,
     *     verses: list<array{verse: int, text: string, cross_references: list<string>}>
     * }>
     */
    private function filteredChapters(RawKnowledgeDocument $rawDocument): array
    {
        $bookFilter = isset($rawDocument->metadata['book']) ? Str::lower((string) $rawDocument->metadata['book']) : null;
        $chapterFilter = isset($rawDocument->metadata['chapter']) ? (int) $rawDocument->metadata['chapter'] : null;

        return array_values(array_filter(
            $this->chapters($rawDocument),
            static fn (array $chapter): bool => ($bookFilter === null || Str::lower($chapter['book']) === $bookFilter)
                && ($chapterFilter === null || $chapter['chapter'] === $chapterFilter),
        ));
    }

    /**
     * @return list<array{
     *     book: string,
     *     book_abbreviation: string|null,
     *     testament: string|null,
     *     chapter: int,
     *     verses: list<array{verse: int, text: string, cross_references: list<string>}>
     * }>
     */
    private function chapters(RawKnowledgeDocument $rawDocument): array
    {
        $payload = $rawDocument->jsonPayload();

        if (isset($payload['book'], $payload['chapter'], $payload['verses'])) {
            return [$this->chapterFromPayload($payload)];
        }

        if (isset($payload['book'], $payload['chapters'])) {
            return $this->chaptersFromBookPayload($payload);
        }

        $chapters = [];

        foreach ($payload['books'] ?? [] as $book) {
            foreach ($book['chapters'] ?? [] as $chapter) {
                $chapters[] = $this->chapterFromPayload(array_merge($chapter, [
                    'book' => $book['book'] ?? $book['name'] ?? '',
                    'book_abbreviation' => $book['book_abbreviation'] ?? $book['abbreviation'] ?? null,
                    'testament' => $book['testament'] ?? null,
                ]));
            }
        }

        return $chapters;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{
     *     book: string,
     *     book_abbreviation: string|null,
     *     testament: string|null,
     *     chapter: int,
     *     verses: list<array{verse: int, text: string, cross_references: list<string>}>
     * }>
     */
    private function chaptersFromBookPayload(array $payload): array
    {
        $chapters = [];

        foreach ($payload['chapters'] ?? [] as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            $chapters[] = $this->chapterFromPayload(array_merge($chapter, [
                'book' => $payload['book'] ?? $payload['short_title'] ?? '',
                'book_abbreviation' => $payload['book_abbreviation'] ?? $payload['abbreviation'] ?? null,
                'testament' => $payload['testament'] ?? null,
            ]));
        }

        return $chapters;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     book: string,
     *     book_abbreviation: string|null,
     *     testament: string|null,
     *     chapter: int,
     *     verses: list<array{verse: int, text: string, cross_references: list<string>}>
     * }
     */
    private function chapterFromPayload(array $payload): array
    {
        $verses = [];

        foreach ($payload['verses'] ?? [] as $verse) {
            $verses[] = [
                'verse' => (int) ($verse['verse'] ?? $verse['number'] ?? 0),
                'text' => (string) ($verse['text'] ?? $verse['content'] ?? ''),
                'cross_references' => array_values(array_map(
                    static fn (mixed $reference): string => (string) $reference,
                    (array) ($verse['cross_references'] ?? $verse['crossReferences'] ?? []),
                )),
            ];
        }

        return [
            'book' => (string) ($payload['book'] ?? ''),
            'book_abbreviation' => isset($payload['book_abbreviation']) ? (string) $payload['book_abbreviation'] : null,
            'testament' => isset($payload['testament']) ? (string) $payload['testament'] : null,
            'chapter' => (int) ($payload['chapter'] ?? $payload['number'] ?? 0),
            'verses' => $verses,
        ];
    }

    private function translation(RawKnowledgeDocument $rawDocument): string
    {
        $payload = $rawDocument->jsonPayload();
        $translation = $rawDocument->metadata['translation']
            ?? $payload['translation']
            ?? $payload['translation_id']
            ?? null;

        if ($translation !== null && $translation !== '') {
            return Str::of((string) $translation)->lower()->replace('-', '_')->replace(' ', '_')->toString();
        }

        if (str_contains(Str::lower($rawDocument->path), 'douay')) {
            return 'douay_rheims';
        }

        return 'unknown';
    }

    private function sourceName(string $translation): string
    {
        return match ($translation) {
            'douay_rheims' => 'Douay-Rheims Bible',
            'unknown' => 'Bible',
            default => Str::of($translation)->replace('_', ' ')->title()->append(' Bible')->toString(),
        };
    }

    private function payloadLanguage(RawKnowledgeDocument $rawDocument): string
    {
        $payload = $rawDocument->jsonPayload();

        return (string) ($rawDocument->metadata['language'] ?? $payload['language'] ?? $this->supportedLanguages()[0]);
    }

    private function sourceEdition(RawKnowledgeDocument $rawDocument): ?string
    {
        $payload = $rawDocument->jsonPayload();

        return isset($rawDocument->metadata['source_edition'])
            ? (string) $rawDocument->metadata['source_edition']
            : (isset($payload['source_edition']) ? (string) $payload['source_edition'] : null);
    }

    /** @param list<array{verse: int, reference: string, text: string, cross_references: list<string>}> $orderedVerses */
    private function chapterCrossReferences(array $orderedVerses): array
    {
        $crossReferences = [];

        foreach ($orderedVerses as $verse) {
            foreach ($verse['cross_references'] as $crossReference) {
                $crossReferences[] = $crossReference;
            }
        }

        return array_values(array_unique($crossReferences));
    }
}
