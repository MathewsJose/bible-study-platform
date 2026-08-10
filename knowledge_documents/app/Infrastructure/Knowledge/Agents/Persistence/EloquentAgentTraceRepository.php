<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Agents\Persistence;

use App\Application\Knowledge\Agents\DTOs\AgentAction;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use App\Application\Knowledge\Agents\Observability\DTOs\AgentTraceData;
use App\Application\Knowledge\Agents\Observability\Services\FailureClassifier;
use App\Application\Knowledge\Agents\Observability\Services\TracePayloadSanitizer;
use App\Application\Knowledge\Agents\Replay\Services\ExecutionFingerprintService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final readonly class EloquentAgentTraceRepository implements AgentTraceRepositoryInterface
{
    public function __construct(
        private TracePayloadSanitizer $sanitizer,
        private FailureClassifier $failures,
        private ExecutionFingerprintService $fingerprints,
    ) {}

    public function startExecution(AgentState $state): ?AgentExecutionRecord
    {
        if (! $this->enabled() || ! $this->schemaReady()) {
            return null;
        }

        $fingerprint = $this->fingerprints->forProfile($state->profile->identifier);

        return AgentExecutionRecord::query()->create([
            'id' => $state->agentId,
            'request_id' => $state->request->id(),
            'profile' => $state->profile->identifier,
            'status' => 'running',
            'started_at' => $state->startedAt ?? CarbonImmutable::now(),
            'input_metadata' => $this->sanitizer->requestMetadata($state->request),
            'metadata' => [
                'profile' => $state->profile->toArray(),
                'tracing' => [
                    'store_inputs' => (bool) config('agent_observability.tracing.store_inputs', false),
                    'store_outputs' => (bool) config('agent_observability.tracing.store_outputs', false),
                ],
                'dataset_versions' => config('agent_observability.evaluation.dataset_versions', []),
                'replay' => [
                    'execution_fingerprint' => $fingerprint,
                    'replay_ready' => (bool) config('agent_observability.tracing.store_inputs', false),
                    'exact_model_replay_guaranteed' => false,
                ],
            ],
        ]);
    }

    public function recordStepStarted(AgentExecutionRecord $execution, AgentState $state, AgentAction $action): ?AgentExecutionStepRecord
    {
        if (! $this->enabled() || ! $this->schemaReady()) {
            return null;
        }

        return AgentExecutionStepRecord::query()->create([
            'agent_execution_id' => $execution->id,
            'step_number' => $state->currentStep + 1,
            'action_type' => 'tool',
            'tool_name' => $action->tool,
            'status' => 'running',
            'started_at' => CarbonImmutable::now(),
            'input_metadata' => $this->sanitizer->actionInput($action),
            'metadata' => ['reason' => $action->reason],
        ]);
    }

    public function recordStepCompleted(AgentExecutionStepRecord $step, ToolResult $result): void
    {
        if (! $this->enabled() || ! $this->schemaReady()) {
            return;
        }

        $step->update([
            'status' => $result->status,
            'failure_category' => $result->successful ? null : $this->failures->classifyToolResult($result),
            'completed_at' => CarbonImmutable::now(),
            'duration_ms' => $result->latencyMs,
            'output_metadata' => $this->sanitizer->toolOutput($result),
            'validation_errors' => $result->warnings,
            'error_information' => $result->error === null ? null : ['message' => $this->sanitizer->sanitize(['error' => $result->error])['error']],
        ]);
    }

    public function completeExecution(AgentState $state): void
    {
        if (! $this->enabled() || ! $this->schemaReady()) {
            return;
        }

        $this->updateExecution($state, null);
    }

    public function failExecution(AgentState $state, string $failureCategory): void
    {
        if (! $this->enabled() || ! $this->schemaReady()) {
            return;
        }

        $this->updateExecution($state, $failureCategory);
    }

    public function find(string $executionId): ?AgentTraceData
    {
        if (! $this->schemaReady()) {
            return null;
        }

        $record = AgentExecutionRecord::query()
            ->with('steps')
            ->find($executionId);

        if (! $record instanceof AgentExecutionRecord) {
            return null;
        }

        return new AgentTraceData(
            execution: $this->executionArray($record),
            steps: $record->steps
                ->map(fn (AgentExecutionStepRecord $step): array => $this->stepArray($step))
                ->values()
                ->all(),
        );
    }

    public function health(?int $days = null): array
    {
        if (! $this->schemaReady()) {
            return $this->emptyHealth(schemaAvailable: false);
        }

        $query = AgentExecutionRecord::query();
        $this->applyDateFilter($query, $days);

        $total = (clone $query)->count();
        $successful = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', '!=', 'completed')->count();
        $averageLatency = (float) ((clone $query)->avg('duration_ms') ?? 0.0);
        $averageSteps = (float) ((clone $query)->avg('step_count') ?? 0.0);
        $averageToolCalls = (float) ((clone $query)->avg('tool_call_count') ?? 0.0);
        $executionIds = (clone $query)->pluck('id')->all();

        $steps = AgentExecutionStepRecord::query()
            ->when($executionIds !== [], fn (Builder $stepQuery): Builder => $stepQuery->whereIn('agent_execution_id', $executionIds));

        return [
            'total_executions' => $total,
            'schema_available' => true,
            'successful_executions' => $successful,
            'failed_executions' => $failed,
            'average_latency_ms' => round($averageLatency, 2),
            'average_steps' => round($averageSteps, 2),
            'average_tool_calls' => round($averageToolCalls, 2),
            'most_used_tools' => $this->toolCounts(clone $steps),
            'most_failed_tools' => $this->toolCounts((clone $steps)->whereNotNull('failure_category')),
            'recent_failures' => AgentExecutionRecord::query()
                ->where('status', '!=', 'completed')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (AgentExecutionRecord $record): array => [
                    'id' => $record->id,
                    'request_id' => $record->request_id,
                    'status' => $record->status,
                    'failure_category' => $record->failure_category,
                    'created_at' => $record->created_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    public function pruneOlderThan(int $days, int $limit): int
    {
        if (! $this->schemaReady()) {
            return 0;
        }

        $cutoff = CarbonImmutable::now()->subDays(max(1, $days));

        $ids = AgentExecutionRecord::query()
            ->where('created_at', '<', $cutoff)
            ->oldest()
            ->limit(max(1, $limit))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return AgentExecutionRecord::query()->whereIn('id', $ids)->delete();
    }

    private function updateExecution(AgentState $state, ?string $failureCategory): void
    {
        $record = AgentExecutionRecord::query()->find($state->agentId);

        if (! $record instanceof AgentExecutionRecord) {
            return;
        }

        $answer = $this->answerResult($state);
        $diagnostics = is_array($answer?->data['diagnostics'] ?? null) ? (array) $answer->data['diagnostics'] : [];
        $metrics = is_array($diagnostics['metrics'] ?? null) ? (array) $diagnostics['metrics'] : [];
        $timings = is_array($diagnostics['timings_ms'] ?? null) ? (array) $diagnostics['timings_ms'] : [];

        $record->update([
            'status' => $state->status,
            'failure_category' => $failureCategory ?? ($state->status === 'completed' ? null : $this->failures->classifyStatus($state->status)),
            'completed_at' => $state->completedAt ?? CarbonImmutable::now(),
            'duration_ms' => $state->elapsedMs(),
            'step_count' => $state->currentStep,
            'tool_call_count' => count($state->toolResults),
            'provider' => is_string($answer?->data['provider'] ?? null) ? $answer->data['provider'] : null,
            'model' => is_string($answer?->data['model'] ?? null) ? $answer->data['model'] : null,
            'prompt_tokens' => isset($answer?->data['prompt_tokens']) ? (int) $answer->data['prompt_tokens'] : null,
            'completion_tokens' => isset($answer?->data['completion_tokens']) ? (int) $answer->data['completion_tokens'] : null,
            'total_tokens' => isset($answer?->data['prompt_tokens'], $answer->data['completion_tokens']) ? (int) $answer->data['prompt_tokens'] + (int) $answer->data['completion_tokens'] : null,
            'retrieval_metrics' => [
                'profile' => $answer?->data['metadata']['retrieval_profile'] ?? $state->profile->retrievalProfile,
                'retrieval_latency_ms' => $timings['retrieval'] ?? null,
                'context_size' => is_array($answer?->data['supporting_documents'] ?? null) ? count((array) $answer->data['supporting_documents']) : null,
            ],
            'answer_metrics' => [
                'provider_latency_ms' => $metrics['provider_latency_ms'] ?? null,
                'prompt_tokens' => $metrics['prompt_tokens'] ?? null,
                'completion_tokens' => $metrics['completion_tokens'] ?? null,
                'citation_count' => $metrics['citations'] ?? null,
                'confidence' => $metrics['confidence'] ?? null,
                'warnings' => $this->sanitizer->sanitize((array) ($answer?->data['warnings'] ?? [])),
            ],
            'error_information' => $state->errors === [] ? null : ['messages' => $this->sanitizer->sanitize($state->errors)],
        ]);
    }

    private function answerResult(AgentState $state): ?ToolResult
    {
        for ($index = count($state->toolResults) - 1; $index >= 0; $index--) {
            $result = $state->toolResults[$index];

            if ($result->tool === 'answer_generation') {
                return $result;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function executionArray(AgentExecutionRecord $record): array
    {
        return [
            'id' => $record->id,
            'request_id' => $record->request_id,
            'profile' => $record->profile,
            'status' => $record->status,
            'failure_category' => $record->failure_category,
            'started_at' => $record->started_at?->toISOString(),
            'completed_at' => $record->completed_at?->toISOString(),
            'duration_ms' => $record->duration_ms,
            'step_count' => $record->step_count,
            'tool_call_count' => $record->tool_call_count,
            'provider' => $record->provider,
            'model' => $record->model,
            'prompt_tokens' => $record->prompt_tokens,
            'completion_tokens' => $record->completion_tokens,
            'total_tokens' => $record->total_tokens,
            'retrieval_metrics' => $record->retrieval_metrics,
            'answer_metrics' => $record->answer_metrics,
            'error_information' => $record->error_information,
            'metadata' => $record->metadata,
        ];
    }

    /** @return array<string, mixed> */
    private function stepArray(AgentExecutionStepRecord $step): array
    {
        return [
            'id' => $step->id,
            'step_number' => $step->step_number,
            'action_type' => $step->action_type,
            'tool_name' => $step->tool_name,
            'status' => $step->status,
            'failure_category' => $step->failure_category,
            'started_at' => $step->started_at?->toISOString(),
            'completed_at' => $step->completed_at?->toISOString(),
            'duration_ms' => $step->duration_ms,
            'input_metadata' => $step->input_metadata,
            'output_metadata' => $step->output_metadata,
            'validation_errors' => $step->validation_errors,
            'error_information' => $step->error_information,
            'metadata' => $step->metadata,
        ];
    }

    /**
     * @return list<array{tool: string, count: int}>
     */
    private function toolCounts(Builder $query): array
    {
        return $query
            ->select('tool_name')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('tool_name')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->get()
            ->map(static fn (AgentExecutionStepRecord $record): array => [
                'tool' => $record->tool_name,
                'count' => (int) $record->getAttribute('aggregate'),
            ])
            ->all();
    }

    private function applyDateFilter(Builder $query, ?int $days): void
    {
        if ($days !== null) {
            $query->where('created_at', '>=', CarbonImmutable::now()->subDays(max(1, $days)));
        }
    }

    private function enabled(): bool
    {
        return (bool) config('agent_observability.tracing.enabled', true);
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('agent_executions') && Schema::hasTable('agent_execution_steps');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyHealth(bool $schemaAvailable): array
    {
        return [
            'schema_available' => $schemaAvailable,
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_latency_ms' => 0.0,
            'average_steps' => 0.0,
            'average_tool_calls' => 0.0,
            'most_used_tools' => [],
            'most_failed_tools' => [],
            'recent_failures' => [],
        ];
    }
}
