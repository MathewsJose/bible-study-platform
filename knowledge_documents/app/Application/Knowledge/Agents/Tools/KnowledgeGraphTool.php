<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Tools;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Domain\Knowledge\Enums\RelationshipType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class KnowledgeGraphTool extends AbstractAgentTool
{
    public function __construct(private KnowledgeGraphRepositoryInterface $graph) {}

    public function name(): string
    {
        return 'knowledge_graph';
    }

    public function displayName(): string
    {
        return 'Knowledge Graph';
    }

    public function description(): string
    {
        return 'Traverse explicit relationships between knowledge documents.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_id' => ['type' => 'string'],
                'relationship_types' => ['type' => 'array'],
                'depth' => ['type' => 'integer'],
                'limit' => ['type' => 'integer'],
            ],
            'rules' => [
                'document_id' => ['required', 'string'],
                'relationship_types' => ['sometimes', 'array'],
                'relationship_types.*' => ['string', 'in:'.implode(',', RelationshipType::values())],
                'depth' => ['sometimes', 'integer', 'min:1', 'max:3'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ],
        ];
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        $started = hrtime(true);
        $documentId = (string) $invocation->arguments['document_id'];
        $relationships = $this->graph->traverse(
            documentId: $documentId,
            depth: (int) ($invocation->arguments['depth'] ?? 1),
            relationshipTypes: array_values(array_map('strval', (array) ($invocation->arguments['relationship_types'] ?? []))),
            limit: (int) ($invocation->arguments['limit'] ?? 50),
        );

        $results = [];
        foreach ($relationships as $relationship) {
            $related = $relationship->source_document_id === $documentId
                ? $relationship->targetDocument
                : $relationship->sourceDocument;

            $results[] = [
                'relationship_id' => $relationship->id,
                'relationship_type' => $relationship->relationship_type,
                'confidence' => $relationship->confidence,
                'document' => $related instanceof KnowledgeDocumentRecord ? [
                    'id' => $related->id,
                    'reference' => $related->reference,
                    'title' => $related->title,
                    'source_type' => $related->source_type,
                ] : null,
            ];
        }

        return $this->success('success', [
            'document_id' => $documentId,
            'results' => $results,
            'total' => count($results),
        ], $started);
    }
}
