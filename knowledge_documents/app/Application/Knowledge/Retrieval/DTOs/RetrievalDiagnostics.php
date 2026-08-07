<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\DTOs;

final readonly class RetrievalDiagnostics
{
    /**
     * @param  array<string, int>  $timingsMs
     * @param  array<string, int|float|string>  $metrics
     */
    public function __construct(
        public array $timingsMs,
        public array $metrics,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'timings_ms' => $this->timingsMs,
            'metrics' => $this->metrics,
        ];
    }
}
