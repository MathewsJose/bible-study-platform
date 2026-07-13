<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class ImportResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failures = 0,
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->failures;
    }
}
