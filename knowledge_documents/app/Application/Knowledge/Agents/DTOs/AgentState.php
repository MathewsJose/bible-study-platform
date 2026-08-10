<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

use Carbon\CarbonImmutable;

final readonly class AgentState
{
    /**
     * @param  list<AgentAction>  $actions
     * @param  list<ToolResult>  $toolResults
     * @param  list<AgentTraceEntry>  $trace
     * @param  list<string>  $errors
     * @param  list<string>  $observations
     */
    public function __construct(
        public string $agentId,
        public AgentRequest $request,
        public AgentProfile $profile,
        public int $currentStep = 0,
        public array $actions = [],
        public array $toolResults = [],
        public array $trace = [],
        public array $errors = [],
        public array $observations = [],
        public string $status = 'running',
        public ?CarbonImmutable $startedAt = null,
        public ?CarbonImmutable $completedAt = null,
    ) {}

    public static function start(string $agentId, AgentRequest $request, AgentProfile $profile): self
    {
        return new self(
            agentId: $agentId,
            request: $request,
            profile: $profile,
            startedAt: CarbonImmutable::now(),
            trace: [new AgentTraceEntry('agent_started', 'running', 0, context: ['profile' => $profile->identifier])],
        );
    }

    public function withTrace(AgentTraceEntry $entry): self
    {
        return new self(
            agentId: $this->agentId,
            request: $this->request,
            profile: $this->profile,
            currentStep: $this->currentStep,
            actions: $this->actions,
            toolResults: $this->toolResults,
            trace: [...$this->trace, $entry],
            errors: $this->errors,
            observations: $this->observations,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
        );
    }

    public function withAction(AgentAction $action): self
    {
        return new self(
            agentId: $this->agentId,
            request: $this->request,
            profile: $this->profile,
            currentStep: $this->currentStep + 1,
            actions: [...$this->actions, $action],
            toolResults: $this->toolResults,
            trace: $this->trace,
            errors: $this->errors,
            observations: $this->observations,
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
        );
    }

    public function withToolResult(ToolResult $result): self
    {
        return new self(
            agentId: $this->agentId,
            request: $this->request,
            profile: $this->profile,
            currentStep: $this->currentStep,
            actions: $this->actions,
            toolResults: [...$this->toolResults, $result],
            trace: $this->trace,
            errors: $result->successful ? $this->errors : [...$this->errors, $result->error ?? $result->status],
            observations: [...$this->observations, $result->status],
            status: $this->status,
            startedAt: $this->startedAt,
            completedAt: $this->completedAt,
        );
    }

    public function complete(string $status = 'completed'): self
    {
        return new self(
            agentId: $this->agentId,
            request: $this->request,
            profile: $this->profile,
            currentStep: $this->currentStep,
            actions: $this->actions,
            toolResults: $this->toolResults,
            trace: [...$this->trace, new AgentTraceEntry($status === 'completed' ? 'agent_completed' : 'agent_failed', $status, $this->currentStep)],
            errors: $this->errors,
            observations: $this->observations,
            status: $status,
            startedAt: $this->startedAt,
            completedAt: CarbonImmutable::now(),
        );
    }

    public function elapsedMs(): int
    {
        $start = $this->startedAt ?? CarbonImmutable::now();
        $end = $this->completedAt ?? CarbonImmutable::now();

        return (int) $start->diffInMilliseconds($end, true);
    }

    public function hasExecuted(AgentAction $action): bool
    {
        $signature = $action->signature();

        foreach ($this->actions as $existing) {
            if ($existing->signature() === $signature) {
                return true;
            }
        }

        return false;
    }
}
