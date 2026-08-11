<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\DTOs;

final readonly class PiiDetection
{
    public function __construct(
        public string $type,
        public int $count,
    ) {}

    /** @return array{type: string, count: int} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'count' => $this->count,
        ];
    }
}
