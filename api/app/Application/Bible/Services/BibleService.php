<?php

namespace App\Application\Bible\Services;

use App\Application\Bible\DTOs\BibleChapterDTO;
use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class BibleService
{
    public function __construct(private readonly VerseRepositoryInterface $repository) {}

    public function getBibleChapter(
        string $language,
        string $version,
        string $book,
        int $chapter
    ): ?BibleChapterDTO {
        $normalizedBook = $this->normalizeBook($book);
        $payload = Cache::remember(
            $this->cacheKey($language, $version, $normalizedBook, $chapter),
            now()->addHours(12),
            fn (): array => $this->buildPayload($language, $version, $normalizedBook, $chapter)
        );

        if ($payload['verses'] === []) {
            return null;
        }

        return new BibleChapterDTO(
            book: $normalizedBook,
            chapter: $chapter,
            version: $version,
            language: $language,
            verses: $payload['verses']
        );
    }

    private function normalizeBook(string $book): string
    {
        return strtolower(trim($book));
    }

    /**
     * @return array{verses: array<int, array{verse: int, text: string}>}
     */
    private function buildPayload(string $language, string $version, string $book, int $chapter): array
    {
        $verses = $this->repository->findChapter($book, $chapter, $language, $version);

        return [
            'verses' => array_map(
                fn ($verse): array => [
                    'verse' => $verse->verse,
                    'text' => $verse->text,
                ],
                $verses
            ),
        ];
    }

    private function cacheKey(string $language, string $version, string $book, int $chapter): string
    {
        return "bible:$language:$version:$book:$chapter";
    }
}
