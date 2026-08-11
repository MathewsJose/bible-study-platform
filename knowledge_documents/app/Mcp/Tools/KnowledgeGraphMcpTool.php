<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

final class KnowledgeGraphMcpTool extends KnowledgeMcpTool
{
    protected function internalName(): string
    {
        return 'knowledge_graph';
    }
}
