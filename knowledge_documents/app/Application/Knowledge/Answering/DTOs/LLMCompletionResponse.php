<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class LLMCompletionResponse
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, int|null>  $usage
     */
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public array $metadata = [],
        public ?string $finishReason = null,
        public array $usage = [],
        public ?float $estimatedCost = null,
    ) {}

    public function totalTokens(): ?int
    {
        if ($this->promptTokens === null || $this->completionTokens === null) {
            return null;
        }

        return $this->promptTokens + $this->completionTokens;
    }
}
