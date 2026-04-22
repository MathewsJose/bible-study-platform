<?php

namespace App\Domain\History\Repositories;

use App\Domain\History\Entities\HistoricalContext;

interface HistoricalContextRepositoryInterface
{
    public function findByReference(
        string $language,
        string $version,
        string $book,
        int $chapter,
        int $verse
    ): ?HistoricalContext;
}
