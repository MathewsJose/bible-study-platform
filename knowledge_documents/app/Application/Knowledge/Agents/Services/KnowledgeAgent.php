<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Services;

use App\Application\Knowledge\Agents\Contracts\AgentInterface;
use App\Application\Knowledge\Agents\Contracts\AgentPlannerInterface;
use App\Application\Knowledge\Agents\DTOs\AgentPlan;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Agents\DTOs\AgentResponse;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\DTOs\AgentTraceEntry;
use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Agents\Events\AgentCompleted;
use App\Application\Knowledge\Agents\Events\AgentFailed;
use App\Application\Knowledge\Agents\Events\AgentStarted;
use App\Application\Knowledge\Agents\Events\AgentStepStarted;
use App\Application\Knowledge\Agents\Events\ToolExecutionCompleted;
use App\Application\Knowledge\Agents\Events\ToolExecutionFailed;
use App\Application\Knowledge\Agents\Events\ToolExecutionStarted;
use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use App\Application\Knowledge\Agents\Observability\Services\FailureClassifier;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionStepRecord;
use Illuminate\Support\Str;
use Throwable;

final readonly class KnowledgeAgent implements AgentInterface
{
    public function __construct(
        private AgentPlannerInterface $planner,
        private AgentProfileRepository $profiles,
        private AgentToolRegistry $tools,
        private AgentGuardrailPolicy $guardrails,
        private AgentTraceRepositoryInterface $traces,
        private FailureClassifier $failures,
    ) {}

    public function execute(AgentRequest $request): AgentResponse
    {
        $state = AgentState::start((string) Str::uuid(), $request, $this->profiles->resolve($request->profile));
        $execution = $this->traces->startExecution($state);
        AgentStarted::dispatch($state);

        while ($state->status === 'running') {
            $plan = $this->plan($state);
            $state = $state->withTrace(new AgentTraceEntry('planner_decision', 'planned', $state->currentStep, context: [
                'decision' => $plan->decision,
                'actions' => array_map(static fn ($action): array => $action->toArray(), $plan->actions),
            ]));

            if ($plan->complete || $plan->actions === []) {
                $state = $state->complete();
                break;
            }

            foreach ($plan->actions as $action) {
                $tool = $this->tools->resolve($action->tool);
                $step = $execution === null ? null : $this->traces->recordStepStarted($execution, $state, $action);
                $violations = $this->guardrails->violations($state, $action, $tool);

                if ($violations !== []) {
                    $result = new ToolResult(
                        tool: $action->tool,
                        successful: false,
                        status: 'guardrail_violation',
                        warnings: $violations,
                        error: implode(' ', $violations),
                    );
                    $this->recordStepCompleted($step, $result);
                    $state = $state->withToolResult($result)->complete('failed');
                    $this->traces->failExecution($state, $this->failures->classifyToolResult($result));
                    AgentFailed::dispatch($state);
                    break 2;
                }

                $state = $state->withAction($action)->withTrace(new AgentTraceEntry('agent_step_started', 'running', $state->currentStep + 1, $action->tool));
                AgentStepStarted::dispatch($state, $action);

                $started = microtime(true);
                ToolExecutionStarted::dispatch($state, $action);

                try {
                    $result = $tool->execute(new ToolInvocation(
                        agentId: $state->agentId,
                        requestId: $request->id(),
                        tool: $action->tool,
                        arguments: $action->arguments,
                        context: ['profile' => $state->profile->identifier],
                    ));
                } catch (Throwable $exception) {
                    $result = new ToolResult(
                        tool: $action->tool,
                        successful: false,
                        status: 'failed',
                        latencyMs: $this->elapsedMs($started),
                        error: $exception->getMessage(),
                    );
                }

                $state = $this->observe($state, $result)->withTrace(new AgentTraceEntry(
                    event: $result->successful ? 'tool_execution_completed' : 'tool_execution_failed',
                    status: $result->status,
                    step: $state->currentStep,
                    tool: $action->tool,
                    latencyMs: $result->latencyMs,
                ));

                $result->successful
                    ? ToolExecutionCompleted::dispatch($state, $result)
                    : ToolExecutionFailed::dispatch($state, $result);
                $this->recordStepCompleted($step, $result);

                if (! $result->successful) {
                    $state = $state->complete('failed');
                    $this->traces->failExecution($state, $this->failures->classifyToolResult($result));
                    AgentFailed::dispatch($state);
                    break 2;
                }

                if ($state->currentStep >= ($request->maxSteps ?? $state->profile->maxSteps)) {
                    $state = $state->complete('max_steps_reached');
                    $this->traces->failExecution($state, FailureClassifier::STEP_LIMIT);
                    break 2;
                }
            }

            $state = $state->complete();
        }

        if ($state->status === 'completed') {
            $this->traces->completeExecution($state);
            AgentCompleted::dispatch($state);
        }

        return $this->finalize($state);
    }

    public function plan(AgentState $state): AgentPlan
    {
        return $this->planner->plan($state);
    }

    public function observe(AgentState $state, ToolResult $result): AgentState
    {
        return $state->withToolResult($result);
    }

    public function finalize(AgentState $state): AgentResponse
    {
        $toolsUsed = array_map(static fn (ToolResult $result): string => $result->tool, $state->toolResults);

        return new AgentResponse(
            agentId: $state->agentId,
            requestId: $state->request->id(),
            status: $state->status,
            answer: $this->finalAnswer($state),
            toolResults: $state->toolResults,
            trace: $state->trace,
            errors: $state->errors,
            diagnostics: [
                'profile' => $state->profile->identifier,
                'duration_ms' => $state->elapsedMs(),
                'steps' => $state->currentStep,
                'tool_calls' => count($state->toolResults),
                'tools_used' => array_values(array_unique($toolsUsed)),
                'average_tool_latency_ms' => count($state->toolResults) === 0 ? 0 : (int) round(array_sum(array_map(static fn (ToolResult $result): int => $result->latencyMs, $state->toolResults)) / count($state->toolResults)),
            ],
            traceId: (bool) config('agent_observability.tracing.enabled', true) ? $state->agentId : null,
        );
    }

    private function finalAnswer(AgentState $state): string
    {
        for ($index = count($state->toolResults) - 1; $index >= 0; $index--) {
            $result = $state->toolResults[$index];

            if (is_string($result->data['answer'] ?? null)) {
                return $result->data['answer'];
            }
        }

        $last = $state->toolResults[array_key_last($state->toolResults)] ?? null;

        if ($last === null) {
            return $state->errors[0] ?? 'No agent action was executed.';
        }

        return $last->successful
            ? "Completed {$last->tool} with status {$last->status}."
            : ($last->error ?? 'Agent failed safely.');
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function recordStepCompleted(?AgentExecutionStepRecord $step, ToolResult $result): void
    {
        if ($step instanceof AgentExecutionStepRecord) {
            $this->traces->recordStepCompleted($step, $result);
        }
    }
}
