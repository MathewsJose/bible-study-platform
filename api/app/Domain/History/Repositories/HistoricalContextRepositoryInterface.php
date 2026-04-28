<?php

namespace App\Domain\History\Repositories;

use App\Domain\History\Entities\HistoricalContext;

interface HistoricalContextRepositoryInterface
{
    /**
     * @return array<int, HistoricalContext>
     */
    public function findChapter(
        string $language,
        string $version,
        string $book,
        int $chapter
    ): array;

    public function findByReference(
        string $language,
        string $version,
        string $book,
        int $chapter,
        ?int $verse
    ): ?HistoricalContext;
}
