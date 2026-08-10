<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Services\SearchKnowledgeDocumentsService;
use App\Domain\Knowledge\Enums\SourceType;

final readonly class CatechismSearchTool extends AbstractAgentTool
{
    public function __construct(private SearchKnowledgeDocumentsService $search) {}

    public function name(): string
    {
        return 'catechism_search';
    }

    public function displayName(): string
    {
        return 'Catechism Search';
    }

    public function description(): string
    {
        return 'Search Catechism of the Catholic Church paragraphs.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
            ],
            'rules' => [
                'query' => ['required', 'string', 'min:1', 'max:500'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ],
        ];
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        $started = hrtime(true);
        $results = $this->search->fullText(
            query: (string) $invocation->arguments['query'],
            limit: (int) ($invocation->arguments['limit'] ?? 10),
            filters: ['source_type' => SourceType::Catechism->value],
        );

        return $this->success('success', [
            'results' => $this->rankedDocuments($results),
            'total' => count($results),
        ], $started);
    }
}
