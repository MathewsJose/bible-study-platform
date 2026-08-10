<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;

final readonly class AdvancedRetrievalTool extends AbstractAgentTool
{
    public function __construct(private RetrievalEngine $retrieval) {}

    public function name(): string
    {
        return 'advanced_retrieval';
    }

    public function displayName(): string
    {
        return 'Advanced Retrieval';
    }

    public function description(): string
    {
        return 'Run the advanced retrieval engine with profile, filters, and top-K controls.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'profile' => ['type' => 'string'],
                'filters' => ['type' => 'object'],
                'top_k' => ['type' => 'integer'],
            ],
            'rules' => [
                'query' => ['required', 'string', 'min:1', 'max:1000'],
                'profile' => ['sometimes', 'string'],
                'filters' => ['sometimes', 'array'],
                'top_k' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ],
        ];
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        $started = hrtime(true);
        $result = $this->retrieval->retrieve(
            query: (string) $invocation->arguments['query'],
            profile: isset($invocation->arguments['profile']) ? (string) $invocation->arguments['profile'] : null,
            filters: (array) ($invocation->arguments['filters'] ?? []),
            topK: isset($invocation->arguments['top_k']) ? (int) $invocation->arguments['top_k'] : null,
        );

        return $this->success('success', $result->toArray(), $started);
    }
}
