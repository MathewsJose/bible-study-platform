<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class ImportResult
{
    public function __construct(
        public int $imported,
        public int $skippedDuplicates,
        public int $failures,
    ) {}
}
