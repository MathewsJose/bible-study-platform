<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Integration\Services;

use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\Integration\DTOs\KnowledgeDocumentSummary;
use App\Application\Knowledge\Integration\DTOs\RelatedKnowledgeItem;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class RelatedKnowledgeIntegrationService
{
    public function __construct(
        private KnowledgeGraphRepositoryInterface $graph,
        private ReferenceResolutionService $references,
    ) {}

    /**
     * @param  list<string>  $relationshipTypes
     * @return array{document: array<string, mixed>, relationships: list<array<string, mixed>>}|null
     */
    public function related(string $document, array $relationshipTypes = [], int $depth = 1, int $limit = 50): ?array
    {
        $summary = $this->references->resolve($document);

        if ($summary === null) {
            $record = KnowledgeDocumentRecord::query()->find($document);
            $summary = $record instanceof KnowledgeDocumentRecord
                ? KnowledgeDocumentSummary::fromDocument(KnowledgeDocumentData::fromRecord($record))
                : null;
        }

        if ($summary === null) {
            return null;
        }

        $relationships = $this->graph->traverse($summary->id, $depth, $relationshipTypes, $limit);
        $items = [];

        foreach ($relationships as $relationship) {
            $related = $relationship->source_document_id === $summary->id
                ? $relationship->targetDocument
                : $relationship->sourceDocument;

            $items[] = new RelatedKnowledgeItem(
                relationshipId: $relationship->id,
                relationshipType: $relationship->relationship_type,
                confidence: $relationship->confidence,
                document: $related instanceof KnowledgeDocumentRecord ? [
                    'id' => $related->id,
                    'reference' => $related->reference,
                    'title' => $related->title,
                    'source_type' => $related->source_type,
                    'source_name' => $related->source_name,
                    'tradition' => $related->tradition,
                ] : null,
            );
        }

        return [
            'document' => $summary->toArray(),
            'relationships' => array_map(static fn (RelatedKnowledgeItem $item): array => $item->toArray(), $items),
        ];
    }
}
