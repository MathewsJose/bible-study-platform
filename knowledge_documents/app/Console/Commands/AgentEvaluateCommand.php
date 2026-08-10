<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\Services\AgentProfileRepository;
use App\Application\Knowledge\Agents\Services\DeterministicAgentPlanner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class AgentEvaluateCommand extends Command
{
    protected $signature = 'agent:evaluate {--profile=catholic_research : Agent profile}';

    protected $description = 'Evaluate deterministic agent tool selection scenarios.';

    public function handle(DeterministicAgentPlanner $planner, AgentProfileRepository $profiles): int
    {
        $profile = $profiles->resolve((string) $this->option('profile'));
        $scenarios = (array) config('agents.evaluation.scenarios', []);
        $rows = [];
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
        }

        $count = count($rows);
        $this->line('Agent Evaluation');
        $this->table(['Scenario', 'Status', 'Expected Tools', 'Actual Tools', 'Planner Latency'], $rows);
        $this->line('Task success rate: '.($count === 0 ? '0.00%' : number_format(($hits / $count) * 100, 2).'%'));
        $this->line('Tool selection accuracy: '.($count === 0 ? '0.00%' : number_format(($hits / $count) * 100, 2).'%'));
        $this->line('Unnecessary tool calls: '.$unnecessary);
        $this->line('Average steps: '.($count === 0 ? '0.00' : number_format($totalSteps / $count, 2)));
        $this->line('Average planner latency: '.($count === 0 ? '0ms' : number_format($totalLatency / $count, 2).'ms'));
        $this->line('Failure rate: '.($count === 0 ? '0.00%' : number_format((($count - $hits) / $count) * 100, 2).'%'));
        $this->line('Groundedness and citation coverage are measured by the answer service after answer_generation executes.');

        return $hits === $count ? self::SUCCESS : self::FAILURE;
    }
}
