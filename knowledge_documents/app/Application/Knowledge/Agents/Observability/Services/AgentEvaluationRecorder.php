<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Observability\Services;

use App\Infrastructure\Knowledge\Agents\Persistence\AgentEvaluationResultRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentEvaluationRunRecord;
use Carbon\CarbonImmutable;

final readonly class AgentEvaluationRecorder
{
    /**
     * @param  list<array<string, mixed>>  $results
     * @param  array<string, mixed>  $summary
     */
    public function persist(string $name, string $profile, array $results, array $summary): AgentEvaluationRunRecord
    {
        $previous = AgentEvaluationRunRecord::query()
            ->where('name', $name)
            ->where('profile', $profile)
            ->latest()
            ->first();
        $regression = $this->regression($previous, $summary);

        $run = AgentEvaluationRunRecord::query()->create([
            'name' => $name,
            'dataset_version' => (string) config('agent_observability.evaluation.dataset_versions.agent', 'agent-v1'),
            'profile' => $profile,
            'started_at' => $summary['started_at'] ?? CarbonImmutable::now(),
            'completed_at' => CarbonImmutable::now(),
            'total_tasks' => (int) $summary['total_tasks'],
            'successful_tasks' => (int) $summary['successful_tasks'],
            'failed_tasks' => (int) $summary['failed_tasks'],
            'success_rate' => (float) $summary['success_rate'],
            'average_steps' => (float) $summary['average_steps'],
            'average_latency_ms' => (float) $summary['average_latency_ms'],
            'unnecessary_tool_calls' => (int) $summary['unnecessary_tool_calls'],
            'regression' => $regression,
            'metadata' => [
                'thresholds' => config('agent_observability.evaluation.regression', []),
            ],
        ]);

        foreach ($results as $result) {
            AgentEvaluationResultRecord::query()->create([
                'agent_evaluation_run_id' => $run->id,
                'scenario_name' => (string) $result['scenario_name'],
                'status' => (string) $result['status'],
                'step_count' => (int) $result['step_count'],
                'latency_ms' => (int) $result['latency_ms'],
                'expected_tools' => (array) $result['expected_tools'],
                'actual_tools' => (array) $result['actual_tools'],
                'missing_tools' => (array) $result['missing_tools'],
                'extra_tools' => (array) $result['extra_tools'],
                'metadata' => (array) ($result['metadata'] ?? []),
            ]);
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function regression(?AgentEvaluationRunRecord $previous, array $summary): array
    {
        if (! $previous instanceof AgentEvaluationRunRecord) {
            return ['compared' => false, 'messages' => []];
        }

        $messages = [];
        $successDrop = $previous->success_rate - (float) $summary['success_rate'];
        $latencyIncrease = $previous->average_latency_ms <= 0
            ? 0.0
            : (((float) $summary['average_latency_ms'] - $previous->average_latency_ms) / $previous->average_latency_ms);

        if ($successDrop > (float) config('agent_observability.evaluation.regression.success_rate_drop', 0.05)) {
            $messages[] = 'Success rate dropped by '.number_format($successDrop * 100, 2).' percentage points.';
        }

        if ($latencyIncrease > (float) config('agent_observability.evaluation.regression.latency_increase_ratio', 0.25)) {
            $messages[] = 'Average latency increased by '.number_format($latencyIncrease * 100, 2).'%.';
        }

        return [
            'compared' => true,
            'previous_run_id' => $previous->id,
            'previous_success_rate' => $previous->success_rate,
            'current_success_rate' => (float) $summary['success_rate'],
            'previous_average_latency_ms' => $previous->average_latency_ms,
            'current_average_latency_ms' => (float) $summary['average_latency_ms'],
            'messages' => $messages,
        ];
    }
}
