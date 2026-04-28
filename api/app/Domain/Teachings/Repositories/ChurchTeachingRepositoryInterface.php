<?php

namespace App\Domain\Teachings\Repositories;

use App\Domain\Teachings\Entities\ChurchTeaching;

interface ChurchTeachingRepositoryInterface
{
    /**
     * @return array<int, ChurchTeaching>
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
    ): ?ChurchTeaching;
}
