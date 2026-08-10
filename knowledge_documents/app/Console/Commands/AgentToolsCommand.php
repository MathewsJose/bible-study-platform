<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use Illuminate\Console\Command;

final class AgentToolsCommand extends Command
{
    protected $signature = 'agent:tools';

    protected $description = 'List registered read-only agent tools.';

    public function handle(AgentToolRegistry $registry): int
    {
        $this->table(
            ['Name', 'Display Name', 'Read Only', 'Timeout', 'Permissions'],
            array_map(static fn (ToolInterface $tool): array => [
                $tool->name(),
                $tool->displayName(),
                $tool->isReadOnly() ? 'yes' : 'no',
                $tool->timeoutSeconds().'s',
                implode(', ', $tool->permissions()),
            ], $registry->all()),
        );

        return self::SUCCESS;
    }
}
