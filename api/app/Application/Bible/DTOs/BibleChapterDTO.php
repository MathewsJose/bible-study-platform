<?php

namespace App\Application\Bible\DTOs;

class BibleChapterDTO
{
    /**
     * @param array<int, array{verse: int, text: string}> $verses
     */
    public function __construct(
        public readonly string $book,
        public readonly int $chapter,
        public readonly string $version,
        public readonly string $language,
        public readonly array $verses
    ) {}
}
