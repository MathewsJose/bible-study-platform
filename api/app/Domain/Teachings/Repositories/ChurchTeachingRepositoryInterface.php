<?php

namespace App\Domain\Teachings\Repositories;

use App\Domain\Teachings\Entities\ChurchTeaching;

interface ChurchTeachingRepositoryInterface
{
    public function findByReference(
        string $language,
        string $version,
        string $book,
        int $chapter,
        int $verse
    ): ?ChurchTeaching;
}
