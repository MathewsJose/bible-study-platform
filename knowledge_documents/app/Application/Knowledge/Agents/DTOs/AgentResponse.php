<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

final readonly class AgentResponse
{
    /**
     * @param  list<ToolResult>  $toolResults
     * @param  list<AgentTraceEntry>  $trace
     * @param  list<string>  $errors
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        public string $agentId,
        public string $requestId,
        public string $status,
        public string $answer,
        public array $toolResults,
        public array $trace,
        public array $errors,
        public array $diagnostics,
        public ?string $traceId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'agent_id' => $this->agentId,
            'request_id' => $this->requestId,
            'status' => $this->status,
            'trace_id' => $this->traceId,
            'answer' => $this->answer,
            'tool_results' => array_map(static fn (ToolResult $result): array => $result->toArray(), $this->toolResults),
            'trace' => array_map(static fn (AgentTraceEntry $entry): array => $entry->toArray(), $this->trace),
            'errors' => $this->errors,
            'diagnostics' => $this->diagnostics,
        ];
    }
}
