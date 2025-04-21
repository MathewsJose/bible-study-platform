<?php

namespace App\Application\Bible\DTOs;

class VerseDTO
{
    public function __construct(
        public readonly string $book,
        public readonly int $chapter,
        public readonly int $verse,
        public readonly string $text
    ) {}
}
