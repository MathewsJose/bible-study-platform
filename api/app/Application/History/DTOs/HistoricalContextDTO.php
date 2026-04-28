<?php

namespace App\Application\History\DTOs;

class HistoricalContextDTO
{
    /**
     * @param array<int, array<string, mixed>|string> $references
     */
    public function __construct(
        public readonly string $book,
        public readonly int $chapter,
        public readonly ?int $verse,
        public readonly array $history,
        public readonly array $items = []
    ) {}
}
