<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use Illuminate\Console\Command;

final class AgentTraceCommand extends Command
{
    protected $signature = 'agent:trace {--id= : Agent execution id}';

    protected $description = 'Inspect a persisted agent execution trace when trace storage is enabled.';

    public function handle(AgentTraceRepositoryInterface $traces): int
    {
        $id = $this->option('id');

        if (! is_string($id) || $id === '') {
            $this->error('Provide an execution id with --id=...');

            return self::FAILURE;
        }

        $trace = $traces->find($id);

        if ($trace === null) {
            $this->error('Agent execution trace not found.');

            return self::FAILURE;
        }

        $execution = $trace->execution;
        $this->line('Agent Trace');
        $this->line('ID: '.$execution['id']);
        $this->line('Request ID: '.$execution['request_id']);
        $this->line('Profile: '.$execution['profile']);
        $this->line('Status: '.$execution['status']);
        $this->line('Failure: '.($execution['failure_category'] ?? 'none'));
        $this->line('Provider/Model: '.($execution['provider'] ?? 'n/a').' / '.($execution['model'] ?? 'n/a'));
        $this->line('Duration: '.$execution['duration_ms'].'ms');
        $this->line('Steps: '.$execution['step_count']);
        $this->line('Tool Calls: '.$execution['tool_call_count']);
        $this->line('');
        $this->line('Timeline');
        $this->table(
            ['Step', 'Tool', 'Status', 'Failure', 'Latency'],
            array_map(static fn (array $step): array => [
                $step['step_number'],
                $step['tool_name'],
                $step['status'],
                $step['failure_category'] ?? '',
                $step['duration_ms'].'ms',
            ], $trace->steps),
        );

        return self::SUCCESS;
    }
}
