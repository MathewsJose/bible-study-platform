<?php

namespace App\Application\Bible\Services;

use App\Application\Bible\DTOs\BibleChapterDTO;
use App\Domain\Bible\Repositories\VerseRepositoryInterface;

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
        $verses = $this->repository->findChapter($normalizedBook, $chapter, $language, $version);

        if ($verses === []) {
            return null;
        }

        return new BibleChapterDTO(
            book: $normalizedBook,
            chapter: $chapter,
            version: $version,
            language: $language,
            verses: array_map(
                fn ($verse): array => [
                    'verse' => $verse->verse,
                    'text' => $verse->text,
                ],
                $verses
            )
        );
    }

    private function normalizeBook(string $book): string
    {
        return strtolower(trim($book));
    }
}
