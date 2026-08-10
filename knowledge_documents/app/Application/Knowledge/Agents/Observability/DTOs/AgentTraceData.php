<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Observability\DTOs;

final readonly class AgentTraceData
{
    /**
     * @param  array<string, mixed>  $execution
     * @param  list<array<string, mixed>>  $steps
     */
    public function __construct(
        public array $execution,
        public array $steps,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'execution' => $this->execution,
            'steps' => $this->steps,
        ];
    }
}
