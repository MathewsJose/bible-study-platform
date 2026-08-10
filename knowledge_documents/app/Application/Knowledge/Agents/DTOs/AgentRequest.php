<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

use Illuminate\Support\Str;

final readonly class AgentRequest
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $allowedTools
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $input,
        public string $profile = 'catholic_research',
        public array $filters = [],
        public array $allowedTools = [],
        public ?int $maxSteps = null,
        public ?int $timeoutSeconds = null,
        public array $metadata = [],
        public string $requestId = '',
    ) {}

    public function id(): string
    {
        return $this->requestId !== '' ? $this->requestId : (string) Str::uuid();
    }
}
