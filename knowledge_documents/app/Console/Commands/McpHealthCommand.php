<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Mcp\Services\McpToolCatalog;
use Illuminate\Console\Command;

final class McpHealthCommand extends Command
{
    protected $signature = 'mcp:health';

    protected $description = 'Show MCP server configuration and tool health.';

    public function handle(McpToolCatalog $catalog): int
    {
        $tools = $catalog->all();

        $this->line('MCP Server Health');
        $this->line('Enabled: '.((bool) config('mcp_knowledge.enabled', false) ? 'yes' : 'no'));
        $this->line('Protocol: Model Context Protocol '.config('mcp_knowledge.protocol_version', '2025-06-18'));
        $this->line('Implementation: laravel/mcp');
        $this->line('Transport: '.config('mcp_knowledge.transport', 'http'));
        $this->line('Route: /'.config('mcp_knowledge.route', 'mcp/knowledge'));
        $this->line('Authentication: '.config('mcp_knowledge.authentication', 'bearer_token'));
        $this->line('Token configured: '.((string) config('mcp_knowledge.token', '') !== '' ? 'yes' : 'no'));
        $this->line('Rate limit: '.config('mcp_knowledge.rate_limit_per_minute', 30).'/minute');
        $this->line('Registered tools: '.count($tools));
        $this->line('Read-only tools: '.count(array_filter($tools, static fn ($tool): bool => $tool->readOnly)));
        $this->line('Write tools: 0');

        return self::SUCCESS;
    }
}
