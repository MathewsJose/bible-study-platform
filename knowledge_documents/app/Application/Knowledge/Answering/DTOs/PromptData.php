<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class PromptData
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        public array $messages,
        public string $systemInstructions,
        public string $contextBlock,
        public int $estimatedTokens,
        public array $diagnostics,
    ) {}
}
