<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use Illuminate\Console\Command;

final class AgentTracesPruneCommand extends Command
{
    protected $signature = 'agent:traces:prune
                            {--days= : Override configured retention days}
                            {--limit= : Maximum traces to prune in one run}';

    protected $description = 'Prune persisted agent traces older than the configured retention window.';

    public function handle(AgentTraceRepositoryInterface $traces): int
    {
        $days = $this->option('days') === null
            ? (int) config('agent_observability.tracing.retention_days', 30)
            : (int) $this->option('days');
        $limit = $this->option('limit') === null
            ? (int) config('agent_observability.tracing.prune_limit', 500)
            : (int) $this->option('limit');

        $deleted = $traces->pruneOlderThan($days, $limit);

        $this->line("Pruned {$deleted} agent trace execution(s).");

        return self::SUCCESS;
    }
}
