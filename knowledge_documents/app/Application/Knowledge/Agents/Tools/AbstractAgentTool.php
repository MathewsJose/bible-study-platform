<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;

abstract readonly class AbstractAgentTool implements ToolInterface
{
    /** @return list<string> */
    public function permissions(): array
    {
        return ['knowledge:read'];
    }

    public function timeoutSeconds(): int
    {
        return (int) config("agents.tools.{$this->name()}.timeout_seconds", config('agents.defaults.tool_timeout_seconds', 10));
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function outputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'results' => ['type' => 'array'],
                'total' => ['type' => 'integer'],
            ],
        ];
    }

    /**
     * @param  list<RankedKnowledgeDocumentData>  $results
     * @return list<array<string, mixed>>
     */
    protected function rankedDocuments(array $results): array
    {
        return array_map(
            static fn (RankedKnowledgeDocumentData $result): array => [
                ...$result->document->toArray(),
                'score' => $result->score,
            ],
            $results,
        );
    }

    protected function success(string $status, array $data, int $startedAt): ToolResult
    {
        return new ToolResult(
            tool: $this->name(),
            successful: true,
            status: $status,
            data: $data,
            latencyMs: $this->elapsedMs($startedAt),
        );
    }

    protected function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
