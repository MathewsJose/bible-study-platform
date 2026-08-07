<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\DTOs;

final readonly class ImportPipelineResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public int $embeddingsQueued = 0,
        public float $durationSeconds = 0.0,
        public array $errors = [],
    ) {}

    public function imported(): int
    {
        return $this->created + $this->updated;
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->failed;
    }
}
