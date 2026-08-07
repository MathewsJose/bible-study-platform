<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Services;

use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class RelatedDocumentsService
{
    public function __construct(private KnowledgeGraphRepositoryInterface $repository) {}

    /**
     * @param  list<string>  $relationshipTypes
     * @return array<string, list<array{id: string, reference: string, title: string, source_type: string, score: float}>>
     */
    public function groupedForDocument(string $documentId, array $relationshipTypes = [], int $limit = 50): array
    {
        $grouped = [];

        foreach ($this->repository->relationshipsForDocument($documentId, $relationshipTypes, $limit) as $relationship) {
            $relatedDocument = $relationship->source_document_id === $documentId
                ? $relationship->targetDocument
                : $relationship->sourceDocument;

            if (! $relatedDocument instanceof KnowledgeDocumentRecord) {
                continue;
            }

            $grouped[$relationship->relationship_type][] = [
                'id' => $relatedDocument->id,
                'reference' => $relatedDocument->reference,
                'title' => $relatedDocument->title,
                'source_type' => $relatedDocument->source_type,
                'score' => $relationship->confidence,
            ];
        }

        return $grouped;
    }
}
