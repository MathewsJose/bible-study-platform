<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class LLMCompletionRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public array $messages,
        public string $model,
        public float $temperature = 0.0,
        public int $maxTokens = 800,
        public array $options = [],
    ) {}
}
