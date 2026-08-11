<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

final class BibleSearchMcpTool extends KnowledgeMcpTool
{
    protected function internalName(): string
    {
        return 'bible_search';
    }
}
