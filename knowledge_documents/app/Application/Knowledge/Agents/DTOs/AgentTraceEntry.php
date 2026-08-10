<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

use Carbon\CarbonImmutable;

final readonly class AgentTraceEntry
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $event,
        public string $status,
        public int $step,
        public ?string $tool = null,
        public int $latencyMs = 0,
        public array $context = [],
        public ?CarbonImmutable $occurredAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'status' => $this->status,
            'step' => $this->step,
            'tool' => $this->tool,
            'latency_ms' => $this->latencyMs,
            'context' => $this->context,
            'occurred_at' => ($this->occurredAt ?? CarbonImmutable::now())->toISOString(),
        ];
    }
}
