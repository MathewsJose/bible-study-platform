<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class ConfidenceData
{
    /**
     * @param  list<string>  $explanations
     * @param  array<string, float>  $signals
     */
    public function __construct(
        public float $score,
        public array $explanations,
        public array $signals,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'explanations' => $this->explanations,
            'signals' => $this->signals,
        ];
    }
}
