<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Services\SearchKnowledgeDocumentsService;
use App\Domain\Knowledge\Enums\SourceType;

final readonly class BibleSearchTool extends AbstractAgentTool
{
    public function __construct(private SearchKnowledgeDocumentsService $search) {}

    public function name(): string
    {
        return 'bible_search';
    }

    public function displayName(): string
    {
        return 'Bible Search';
    }

    public function description(): string
    {
        return 'Search Bible verses and chapters by query, translation, book, or chapter.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'translation' => ['type' => 'string'],
                'book' => ['type' => 'string'],
                'chapter' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
            ],
            'rules' => [
                'query' => ['required', 'string', 'min:1', 'max:500'],
                'translation' => ['sometimes', 'string', 'max:120'],
                'book' => ['sometimes', 'string', 'max:80'],
                'chapter' => ['sometimes', 'integer', 'min:1', 'max:200'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ],
        ];
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        $started = hrtime(true);
        $filters = [
            'source_types' => [SourceType::BibleVerse->value, SourceType::BibleChapter->value],
        ];

        foreach (['book', 'chapter', 'translation'] as $key) {
            if (isset($invocation->arguments[$key])) {
                $filters[$key] = $invocation->arguments[$key];
            }
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
