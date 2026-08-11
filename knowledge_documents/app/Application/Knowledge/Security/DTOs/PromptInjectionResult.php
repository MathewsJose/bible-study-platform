<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\DTOs;

final readonly class PromptInjectionResult
{
    /**
     * @param  list<string>  $signals
     */
    public function __construct(
        public bool $detected,
        public int $score,
        public array $signals,
    ) {}

    /** @return array<string, mixed> */
    public function toSafeArray(): array
    {
        return [
            'detected' => $this->detected,
            'score' => $this->score,
            'signals' => $this->signals,
        ];
    }
}
