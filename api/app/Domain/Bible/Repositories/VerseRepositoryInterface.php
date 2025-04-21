<?php

namespace App\Domain\Bible\Repositories;

use App\Domain\Bible\Entities\Verse;

interface VerseRepositoryInterface
{
    public function findByReference(string $book, int $chapter, int $verse): ?Verse;
    public function save(Verse $verse): void;
}
