<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $tool,
        public bool $successful,
        public string $status,
        public array $data = [],
        public array $warnings = [],
        public array $metadata = [],
        public int $latencyMs = 0,
        public ?string $error = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'successful' => $this->successful,
            'status' => $this->status,
            'data' => $this->data,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
            'latency_ms' => $this->latencyMs,
            'error' => $this->error,
        ];
    }
}
