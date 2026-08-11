<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Mcp\DTOs\McpToolDefinition;
use App\Application\Knowledge\Mcp\Services\McpToolCatalog;
use Illuminate\Console\Command;

final class McpToolsCommand extends Command
{
    protected $signature = 'mcp:tools';

    protected $description = 'List MCP tools exposed by the Knowledge Service.';

    public function handle(McpToolCatalog $catalog): int
    {
        $tools = $catalog->all();

        $this->line('MCP Tools');
        $this->table(
            ['Name', 'Read Only', 'Permissions'],
            array_map(static fn (McpToolDefinition $tool): array => [
                $tool->name,
                $tool->readOnly ? 'yes' : 'no',
                implode(', ', $tool->permissions),
            ], $tools),
        );
        $this->line('Total: '.count($tools));
        $this->line('Write tools: 0');
        $this->line('Read-only tools: '.count(array_filter($tools, static fn (McpToolDefinition $tool): bool => $tool->readOnly)));

        return self::SUCCESS;
    }
}
