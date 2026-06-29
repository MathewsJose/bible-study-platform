<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class EmbeddingGenerationResult
{
    public function __construct(
        public int $processed,
        public int $generated,
        public int $failures,
    ) {}
}
