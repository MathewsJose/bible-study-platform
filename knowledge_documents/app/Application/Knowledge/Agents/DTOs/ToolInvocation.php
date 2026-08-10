<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

final readonly class ToolInvocation
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $agentId,
        public string $requestId,
        public string $tool,
        public array $arguments,
        public array $context = [],
    ) {}
}
