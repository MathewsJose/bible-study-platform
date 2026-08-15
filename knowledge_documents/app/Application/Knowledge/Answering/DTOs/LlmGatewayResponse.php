<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class LlmGatewayResponse
{
    public function __construct(
        public LLMCompletionResponse $completion,
        public LlmModelSelection $selection,
        public bool $usedFallback = false,
        public ?string $warning = null,
    ) {}
}
