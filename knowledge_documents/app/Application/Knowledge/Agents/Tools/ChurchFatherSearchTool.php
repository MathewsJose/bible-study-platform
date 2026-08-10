<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Services\SearchKnowledgeDocumentsService;
use App\Domain\Knowledge\Enums\SourceType;

final readonly class ChurchFatherSearchTool extends AbstractAgentTool
{
    public function __construct(private SearchKnowledgeDocumentsService $search) {}

    public function name(): string
    {
        return 'church_father_search';
    }

    public function displayName(): string
    {
        return 'Church Father Search';
    }

    public function description(): string
    {
        return 'Search imported writings from the Church Fathers.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'author' => ['type' => 'string'],
                'limit' => ['type' => 'integer'],
            ],
            'rules' => [
                'query' => ['required', 'string', 'min:1', 'max:500'],
                'author' => ['sometimes', 'string', 'max:120'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ],
        ];
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        $started = hrtime(true);
        $filters = ['source_type' => SourceType::ChurchFather->value];

        if (isset($invocation->arguments['author'])) {
            $filters['author'] = $invocation->arguments['author'];
        }

        $results = $this->search->fullText(
            query: (string) $invocation->arguments['query'],
            limit: (int) ($invocation->arguments['limit'] ?? 10),
            filters: $filters,
        );

        return $this->success('success', [
            'results' => $this->rankedDocuments($results),
            'total' => count($results),
            'filters' => $filters,
        ], $started);
    }
}
