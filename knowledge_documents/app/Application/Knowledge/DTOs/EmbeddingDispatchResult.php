<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class EmbeddingDispatchResult
{
    public function __construct(
        public int $documentsQueued,
        public int $jobsQueued,
        public int $generated,
        public int $failures,
        public bool $processedSynchronously,
    ) {}
}
