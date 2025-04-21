<?php

namespace App\Domain\Bible\Entities;

class Verse
{
    public function __construct(
        public readonly string $id,
        public readonly string $book,
        public readonly int $chapter,
        public readonly int $verse,
        public readonly string $text
    ) {}
}
