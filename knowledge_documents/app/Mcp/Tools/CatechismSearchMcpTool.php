<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

final class CatechismSearchMcpTool extends KnowledgeMcpTool
{
    protected function internalName(): string
    {
        return 'catechism_search';
    }
}
