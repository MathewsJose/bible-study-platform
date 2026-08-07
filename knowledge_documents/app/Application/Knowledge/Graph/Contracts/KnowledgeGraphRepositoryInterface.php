<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Contracts;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRelationshipRecord;

interface KnowledgeGraphRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $provenance
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(string $sourceDocumentId, string $targetDocumentId, string $relationshipType, float $confidence, array $provenance, array $metadata): KnowledgeDocumentRelationshipRecord;

    public function deleteOutgoing(string $sourceDocumentId): int;

    /**
     * @param  list<string>  $relationshipTypes
     * @return list<KnowledgeDocumentRelationshipRecord>
     */
    public function relationshipsForDocument(string $documentId, array $relationshipTypes = [], int $limit = 50): array;

    /**
     * @param  list<string>  $relationshipTypes
     * @return list<KnowledgeDocumentRelationshipRecord>
     */
    public function traverse(string $documentId, int $depth = 1, array $relationshipTypes = [], int $limit = 100): array;

    /** @return array<string, int> */
    public function relationshipCounts(): array;

    public function totalNodes(): int;

    public function totalEdges(): int;

    public function disconnectedNodeCount(): int;

    public function duplicateRelationshipCount(): int;

    public function brokenRelationshipCount(): int;

    public function averageDegree(): float;

    public function density(): float;
}
