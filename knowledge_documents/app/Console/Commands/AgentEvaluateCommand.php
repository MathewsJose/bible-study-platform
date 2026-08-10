<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\Observability\Services\AgentEvaluationRecorder;
use App\Application\Knowledge\Agents\Services\AgentProfileRepository;
use App\Application\Knowledge\Agents\Services\DeterministicAgentPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class AgentEvaluateCommand extends Command
{
    protected $signature = 'agent:evaluate
                            {--profile=catholic_research : Agent profile}
                            {--save : Persist this evaluation run}
                            {--name=agent-deterministic : Evaluation run name}';

    protected $description = 'Evaluate deterministic agent tool selection scenarios.';

    public function handle(DeterministicAgentPlanner $planner, AgentProfileRepository $profiles, AgentEvaluationRecorder $recorder): int
    {
        $startedAt = CarbonImmutable::now();
        $profile = $profiles->resolve((string) $this->option('profile'));
        $scenarios = (array) config('agents.evaluation.scenarios', []);
        $rows = [];
        $results = [];
        $hits = 0;
        $totalLatency = 0;
        $totalSteps = 0;
        $unnecessary = 0;

        foreach ($scenarios as $scenario) {
            if (! is_array($scenario)) {
                continue;
            }

            $started = hrtime(true);
            $state = AgentState::start((string) Str::uuid(), new AgentRequest((string) $scenario['input'], $profile->identifier), $profile);
            $plan = $planner->plan($state);
            $latency = (int) round((hrtime(true) - $started) / 1_000_000);
            $actual = array_map(static fn ($action): string => $action->tool, $plan->actions);
            $expected = array_values(array_map('strval', (array) ($scenario['expected_tools'] ?? [])));
            $missing = array_values(array_diff($expected, $actual));
            $extra = array_values(array_diff($actual, $expected));
            $passed = $missing === [];

            $hits += $passed ? 1 : 0;
            $totalLatency += $latency;
            $totalSteps += count($actual);
            $unnecessary += count($extra);

            $rows[] = [
                (string) ($scenario['name'] ?? 'Scenario'),
                $passed ? 'pass' : 'fail',
                implode(', ', $expected),
                implode(', ', $actual),
                $latency.'ms',
            ];
            $results[] = [
                'scenario_name' => (string) ($scenario['name'] ?? 'Scenario'),
                'status' => $passed ? 'pass' : 'fail',
                'step_count' => count($actual),
                'latency_ms' => $latency,
                'expected_tools' => $expected,
                'actual_tools' => $actual,
                'missing_tools' => $missing,
                'extra_tools' => $extra,
            ];
        }

        $count = count($rows);
        $summary = [
            'started_at' => $startedAt,
            'total_tasks' => $count,
            'successful_tasks' => $hits,
            'failed_tasks' => $count - $hits,
            'success_rate' => $count === 0 ? 0.0 : $hits / $count,
            'average_steps' => $count === 0 ? 0.0 : $totalSteps / $count,
            'average_latency_ms' => $count === 0 ? 0.0 : $totalLatency / $count,
            'unnecessary_tool_calls' => $unnecessary,
        ];
        $this->line('Agent Evaluation');
        $this->table(['Scenario', 'Status', 'Expected Tools', 'Actual Tools', 'Planner Latency'], $rows);
        $this->line('Task success rate: '.($count === 0 ? '0.00%' : number_format(($hits / $count) * 100, 2).'%'));
        $this->line('Tool selection accuracy: '.($count === 0 ? '0.00%' : number_format(($hits / $count) * 100, 2).'%'));
        $this->line('Unnecessary tool calls: '.$unnecessary);
        $this->line('Average steps: '.($count === 0 ? '0.00' : number_format($totalSteps / $count, 2)));
        $this->line('Average planner latency: '.($count === 0 ? '0ms' : number_format($totalLatency / $count, 2).'ms'));
        $this->line('Failure rate: '.($count === 0 ? '0.00%' : number_format((($count - $hits) / $count) * 100, 2).'%'));
        $this->line('Groundedness and citation coverage are measured by the answer service after answer_generation executes.');

        if ((bool) $this->option('save')) {
            /** @var list<array<string, mixed>> $results */
            $run = $recorder->persist((string) $this->option('name'), $profile->identifier, $results ?? [], $summary);
            $this->line('Saved evaluation run: '.$run->id);
            $regression = (array) $run->regression;
            foreach ((array) ($regression['messages'] ?? []) as $message) {
                $this->warn((string) $message);
            }
        }

        return $hits === $count ? self::SUCCESS : self::FAILURE;
    }
}
