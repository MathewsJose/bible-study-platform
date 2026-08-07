<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class LLMCompletionResponse
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public array $metadata = [],
    ) {}
}
