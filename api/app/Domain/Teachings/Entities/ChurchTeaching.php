<?php

namespace App\Domain\Teachings\Entities;

class ChurchTeaching
{
    /**
     * @param array<int, array<string, mixed>|string> $references
     */
    public function __construct(
        public readonly string $book,
        public readonly int $chapter,
        public readonly int $verse,
        public readonly ?string $summary,
        public readonly ?string $details,
        public readonly string $tradition = 'Catholic',
        public readonly array $references = [],
        public readonly string $language = 'en',
        public readonly string $version = 'nrsvce'
    ) {}
}
