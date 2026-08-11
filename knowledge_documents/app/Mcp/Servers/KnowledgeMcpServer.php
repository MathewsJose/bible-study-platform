<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\AdvancedRetrievalMcpTool;
use App\Mcp\Tools\BibleSearchMcpTool;
use App\Mcp\Tools\CatechismSearchMcpTool;
use App\Mcp\Tools\ChurchFatherSearchMcpTool;
use App\Mcp\Tools\KnowledgeGraphMcpTool;
use App\Mcp\Tools\ScriptureReferenceMcpTool;
use Laravel\Mcp\Server;

final class KnowledgeMcpServer extends Server
{
    protected string $name = 'Catholic Bible Knowledge MCP';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Secure read-only MCP access to the Catholic Bible Knowledge Service. Tools are adapters around the internal AgentToolRegistry.
    MARKDOWN;

    /** @var array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected array $tools = [
        BibleSearchMcpTool::class,
        ScriptureReferenceMcpTool::class,
        CatechismSearchMcpTool::class,
        ChurchFatherSearchMcpTool::class,
        KnowledgeGraphMcpTool::class,
        AdvancedRetrievalMcpTool::class,
    ];

    protected function boot(): void
    {
        $this->name = (string) config('mcp_knowledge.server_name', $this->name);
        $this->version = (string) config('mcp_knowledge.server_version', $this->version);
    }
}
