<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class AgentTraceCommand extends Command
{
    protected $signature = 'agent:trace {--id= : Agent execution id}';

    protected $description = 'Inspect a persisted agent execution trace when trace storage is enabled.';

    public function handle(): int
    {
        $id = $this->option('id');

        $this->line('Agent Trace');
        $this->line('ID: '.($id === null ? 'not provided' : (string) $id));
        $this->warn('Persistent trace storage is not enabled yet. Use the trace returned by POST /api/agents/run or php artisan agent:run output.');

        return self::SUCCESS;
    }
}
