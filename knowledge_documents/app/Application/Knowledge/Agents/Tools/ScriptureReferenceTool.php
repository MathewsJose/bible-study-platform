<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Services\SearchKnowledgeDocumentsService;
use App\Domain\Knowledge\Enums\SourceType;

final readonly class ScriptureReferenceTool extends AbstractAgentTool
{
    public function __construct(private SearchKnowledgeDocumentsService $search) {}

    public function name(): string
    {
        return 'scripture_reference';
    }

    public function displayName(): string
    {
        return 'Scripture Reference';
    }

    public function description(): string
    {
        return 'Resolve explicit Scripture references such as John 1:14.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reference' => ['type' => 'string'],
            ],
            'rules' => [
                'reference' => ['required', 'string', 'min:3', 'max:120'],
            ],
        ];
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        $started = hrtime(true);
        $results = $this->search->fullText(
            query: (string) $invocation->arguments['reference'],
            limit: 10,
            filters: ['source_types' => [SourceType::BibleVerse->value, SourceType::BibleChapter->value]],
        );

        return $this->success('success', [
            'reference' => $invocation->arguments['reference'],
            'results' => $this->rankedDocuments($results),
            'total' => count($results),
        ], $started);
    }
}
