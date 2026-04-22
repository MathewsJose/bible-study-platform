<?php

namespace App\Domain\Bible\Repositories;

use App\Domain\Bible\Entities\Verse;

interface VerseRepositoryInterface
{
    /**
     * @return array<int, Verse>
     */
    public function findChapter(string $book, int $chapter, ?string $language = null, ?string $version = null): array;
    public function findByReference(string $book, int $chapter, int $verse, ?string $language = null, ?string $version = null): ?Verse;
    public function save(Verse $verse): void;
}
