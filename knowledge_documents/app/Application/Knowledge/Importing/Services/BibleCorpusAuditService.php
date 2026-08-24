<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Services;

use App\Infrastructure\Knowledge\Importing\BibleCanon;
use JsonException;

final readonly class BibleCorpusAuditService
{
    public function __construct(private BibleCanon $canon) {}

    /**
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    public function audit(array $paths): array
    {
        $files = [];
        $books = [];
        $references = [];
        $duplicateReferences = [];
        $duplicateReferencesWithinSource = [];
        $malformedReferences = [];
        $invalidChapters = [];
        $invalidVerses = [];
        $emptyVerses = [];
        $shortVerses = [];
        $longVerses = [];
        $invalidCanonicalOrdering = [];
        $unexpectedBooks = [];
        $sourceIdentityWarnings = [];
        $lastCanonicalOrder = 0;
        $totalChapters = 0;
        $totalVerses = 0;

        foreach ($paths as $path) {
            $file = $this->auditFile($path);
            $files[] = $file;
            $sourceIdentityWarnings = array_merge($sourceIdentityWarnings, $file['identity_warnings']);
            $fileReferenceSeen = [];

            foreach ($file['chapters'] as $chapter) {
                $book = (string) $chapter['book'];
                $books[$book] ??= ['chapters' => [], 'verses' => 0];
                $books[$book]['chapters'][(int) $chapter['chapter']] = true;
                $totalChapters++;

                if ((int) $chapter['chapter'] < 1) {
                    $invalidChapters[] = "{$book} {$chapter['chapter']}";
                }

                if (! $this->canon->isValidBook($book)) {
                    $unexpectedBooks[$book] = true;
                }

                foreach ($chapter['verses'] as $verse) {
                    $reference = (string) $verse['reference'];
                    $totalVerses++;
                    $books[$book]['verses']++;

                    if (! $this->validReference($reference)) {
                        $malformedReferences[] = $reference;
                    }

                    if ((int) $verse['verse'] < 1) {
                        $invalidVerses[] = $reference;
                    }

                    if (isset($references[$reference])) {
                        $duplicateReferences[$reference][] = $path;
                    } else {
                        $references[$reference] = [$path];
                    }

                    if (isset($fileReferenceSeen[$reference])) {
                        $duplicateReferencesWithinSource[] = $reference;
                    }
                    $fileReferenceSeen[$reference] = true;

                    $contentLength = mb_strlen(trim((string) $verse['text']));
                    if ($contentLength === 0) {
                        $emptyVerses[] = $reference;
                    } elseif ($contentLength < 12) {
                        $shortVerses[] = "{$reference} ({$contentLength})";
                    } elseif ($contentLength > 600) {
                        $longVerses[] = "{$reference} ({$contentLength})";
                    }

                    if ($this->canon->isValidBook($book) && (int) $chapter['chapter'] > 0 && (int) $verse['verse'] > 0) {
                        $canonicalOrder = $this->canon->canonicalOrder($book, (int) $chapter['chapter'], (int) $verse['verse']);
                        if ($canonicalOrder <= $lastCanonicalOrder) {
                            $invalidCanonicalOrdering[] = $reference;
                        }
                        $lastCanonicalOrder = $canonicalOrder;
                    }
                }
            }
        }

        $expectedBooks = $this->canon->books();
        $foundBooks = array_keys($books);
        sort($foundBooks);
        $missingBooks = array_values(array_diff($expectedBooks, $foundBooks));
        $deuterocanonicalFound = array_values(array_intersect($this->canon->deuterocanonicalBooks(), $foundBooks));

        return [
            'summary' => [
                'files' => count($files),
                'expected_books' => count($expectedBooks),
                'books_found' => count($foundBooks),
                'chapters_found' => $totalChapters,
                'verses_found' => $totalVerses,
                'complete_catholic_canon' => $missingBooks === [] && $unexpectedBooks === [],
                'deuterocanonical_books_found' => count($deuterocanonicalFound),
            ],
            'expected_books' => $expectedBooks,
            'books_found' => $foundBooks,
            'books_missing' => $missingBooks,
            'books_unexpected' => array_keys($unexpectedBooks),
            'deuterocanonical' => [
                'expected' => $this->canon->deuterocanonicalBooks(),
                'found' => $deuterocanonicalFound,
                'missing' => array_values(array_diff($this->canon->deuterocanonicalBooks(), $deuterocanonicalFound)),
            ],
            'book_counts' => $this->bookCounts($books),
            'duplicate_references' => $this->duplicateReferenceRows($duplicateReferences),
            'duplicate_references_within_source' => array_values(array_unique($duplicateReferencesWithinSource)),
            'malformed_references' => array_values(array_unique($malformedReferences)),
            'invalid_chapters' => array_values(array_unique($invalidChapters)),
            'invalid_verses' => array_values(array_unique($invalidVerses)),
            'empty_verses' => array_values(array_unique($emptyVerses)),
            'suspiciously_short_verses' => array_values(array_unique($shortVerses)),
            'suspiciously_long_verses' => array_values(array_unique($longVerses)),
            'invalid_canonical_ordering' => array_values(array_unique($invalidCanonicalOrdering)),
            'source_identity_warnings' => array_values(array_unique($sourceIdentityWarnings)),
            'files' => $files,
            'recommendation' => $missingBooks === [] && $unexpectedBooks === []
                ? 'Source appears canon-complete; verify provenance before import.'
                : 'Source is not ready for full Catholic Bible import.',
        ];
    }

    /**
     * @return array{path: string, readable: bool, format: string|null, errors: list<string>, metadata: array<string, mixed>, identity_warnings: list<string>, chapters: list<array{book: string, chapter: int, verses: list<array{reference: string, verse: int, text: string}>}>}
     */
    private function auditFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [
                'path' => $path,
                'readable' => false,
                'format' => null,
                'errors' => ["Unable to read [{$path}]."],
                'metadata' => [],
                'identity_warnings' => [],
                'chapters' => [],
            ];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [
                'path' => $path,
                'readable' => true,
                'format' => 'invalid_json',
                'errors' => [$exception->getMessage()],
                'metadata' => [],
                'identity_warnings' => [],
                'chapters' => [],
            ];
        }

        if (! is_array($payload)) {
            return [
                'path' => $path,
                'readable' => true,
                'format' => 'unknown',
                'errors' => ['Bible file must contain a JSON object.'],
                'metadata' => [],
                'identity_warnings' => [],
                'chapters' => [],
            ];
        }

        $chapters = isset($payload['books'])
            ? $this->chaptersFromBooksPayload($payload)
            : [$this->chapterFromPayload($payload)];
        $metadata = [
            'translation' => $payload['translation'] ?? $payload['translation_id'] ?? null,
            'language' => $payload['language'] ?? null,
            'source_url' => $payload['source_url'] ?? null,
            'license' => $payload['license'] ?? null,
            'license_url' => $payload['license_url'] ?? null,
            'source_edition' => $payload['source_edition'] ?? null,
        ];

        return [
            'path' => $path,
            'readable' => true,
            'format' => isset($payload['books']) ? 'multi_book_json' : 'single_chapter_json',
            'errors' => [],
            'metadata' => $metadata,
            'identity_warnings' => $this->sourceIdentityWarnings($path, $metadata),
            'chapters' => array_values(array_filter($chapters, static fn (array $chapter): bool => $chapter['book'] !== '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{book: string, chapter: int, verses: list<array{reference: string, verse: int, text: string}>}>
     */
    private function chaptersFromBooksPayload(array $payload): array
    {
        $chapters = [];

        foreach ((array) ($payload['books'] ?? []) as $book) {
            if (! is_array($book)) {
                continue;
            }

            foreach ((array) ($book['chapters'] ?? []) as $chapter) {
                if (! is_array($chapter)) {
                    continue;
                }

                $chapters[] = $this->chapterFromPayload(array_merge($chapter, [
                    'book' => $book['book'] ?? $book['name'] ?? '',
                ]));
            }
        }

        return $chapters;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{book: string, chapter: int, verses: list<array{reference: string, verse: int, text: string}>}
     */
    private function chapterFromPayload(array $payload): array
    {
        $book = (string) ($payload['book'] ?? '');
        $chapter = (int) ($payload['chapter'] ?? $payload['number'] ?? 0);
        $verses = [];

        foreach ((array) ($payload['verses'] ?? []) as $verse) {
            if (! is_array($verse)) {
                continue;
            }

            $verseNumber = (int) ($verse['verse'] ?? $verse['number'] ?? 0);
            $verses[] = [
                'reference' => "{$book} {$chapter}:{$verseNumber}",
                'verse' => $verseNumber,
                'text' => (string) ($verse['text'] ?? $verse['content'] ?? ''),
            ];
        }

        return [
            'book' => $book,
            'chapter' => $chapter,
            'verses' => $verses,
        ];
    }

    private function validReference(string $reference): bool
    {
        return preg_match('/^[1-3]?\s?[A-Za-z][A-Za-z ]+\s+\d+:\d+$/', $reference) === 1;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    private function sourceIdentityWarnings(string $path, array $metadata): array
    {
        $warnings = [];
        $file = basename($path);

        foreach ([
            'translation',
            'language',
            'source_url',
            'license',
            'license_url',
            'source_edition',
        ] as $key) {
            if (($metadata[$key] ?? null) === null || $metadata[$key] === '') {
                $warnings[] = "{$file}: {$key} is missing.";
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, array{chapters: array<int, bool>, verses: int}>  $books
     * @return array<string, array{chapters: int, verses: int}>
     */
    private function bookCounts(array $books): array
    {
        ksort($books);

        return array_map(static fn (array $counts): array => [
            'chapters' => count($counts['chapters']),
            'verses' => $counts['verses'],
        ], $books);
    }

    /**
     * @param  array<string, list<string>>  $duplicates
     * @return list<array{reference: string, occurrences: int, files: list<string>}>
     */
    private function duplicateReferenceRows(array $duplicates): array
    {
        $rows = [];

        foreach ($duplicates as $reference => $files) {
            $rows[] = [
                'reference' => $reference,
                'occurrences' => count($files),
                'files' => array_values(array_unique($files)),
            ];
        }

        return $rows;
    }
}
