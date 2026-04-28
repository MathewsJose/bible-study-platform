<?php

namespace App\Application\Teachings\DTOs;

class ChurchTeachingDTO
{
    public function __construct(
        public readonly string $book,
        public readonly int $chapter,
        public readonly ?int $verse,
        public readonly array $teachings,
        public readonly array $items = []
    ) {}
}
