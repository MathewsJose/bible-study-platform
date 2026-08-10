<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Services\AgentProfileRepository;
use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use Illuminate\Console\Command;

final class AgentHealthCommand extends Command
{
    protected $signature = 'agent:health {--days= : Restrict persisted execution statistics to the last N days}';

    protected $description = 'Show agent framework configuration and diagnostics.';

    public function handle(AgentToolRegistry $tools, AgentProfileRepository $profiles, AgentTraceRepositoryInterface $traces): int
    {
        $this->line('Agent Framework Health');
        $this->line('Planner: '.config('agents.planner', 'deterministic'));
        $this->line('Default profile: '.config('agents.default_profile', 'catholic_research'));
        $this->line('Registered tools: '.count($tools->all()));
        $this->line('Agent profiles: '.count($profiles->all()));
        $this->line('');

        $this->table(
            ['Profile', 'Max Steps', 'Max Tool Calls', 'Retrieval', 'Answer'],
            array_map(static fn ($profile): array => [
                $profile->identifier,
                $profile->maxSteps,
                $profile->maxToolCalls,
                $profile->retrievalProfile,
                $profile->answerProfile,
            ], $profiles->all()),
        );

        $stats = $traces->health($this->option('days') === null ? null : (int) $this->option('days'));

        $this->line('');
        $this->line('Persistent Execution Statistics');
        $this->line('Trace persistence: '.((bool) config('agent_observability.tracing.enabled', true) ? 'enabled' : 'disabled'));

        if (($stats['schema_available'] ?? true) === false) {
            $this->warn('Trace tables are not available yet.');
            $this->line('Run: php artisan migrate');
            $this->line('');
        }

        $this->line('Total executions: '.$stats['total_executions']);
        $this->line('Successful executions: '.$stats['successful_executions']);
        $this->line('Failed executions: '.$stats['failed_executions']);
        $this->line('Average latency: '.$stats['average_latency_ms'].'ms');
        $this->line('Average steps: '.$stats['average_steps']);
        $this->line('Average tool calls: '.$stats['average_tool_calls']);

        $this->table(['Tool', 'Uses'], array_map(static fn (array $row): array => [$row['tool'], $row['count']], (array) $stats['most_used_tools']));
        $this->table(['Tool', 'Failures'], array_map(static fn (array $row): array => [$row['tool'], $row['count']], (array) $stats['most_failed_tools']));

        return self::SUCCESS;
    }
}
