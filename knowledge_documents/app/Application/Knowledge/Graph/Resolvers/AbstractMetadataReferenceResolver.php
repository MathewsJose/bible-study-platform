<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Resolvers;

use App\Application\Knowledge\Graph\DTOs\ResolvedRelationship;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

abstract class AbstractMetadataReferenceResolver
{
    /** @return list<ResolvedRelationship> */
    public function resolve(KnowledgeDocumentRecord $document): array
    {
        $relationships = [];

        foreach ($this->references($document) as $reference) {
            $target = $this->resolveTarget($document, $reference);
            if (! $target instanceof KnowledgeDocumentRecord || $target->id === $document->id) {
                continue;
            }

            $relationships[] = new ResolvedRelationship(
                sourceDocumentId: $document->id,
                targetDocumentId: $target->id,
                relationshipType: $this->relationshipType($document, $target, $reference),
                confidence: 1.0,
                provenance: [
                    'source' => 'document_metadata',
                    'resolver' => $this->identifier(),
                    'reference' => $reference,
                ],
                metadata: [
                    'reference' => $reference,
                    'explicit' => true,
                ],
            );
        }

        return $relationships;
    }

    /** @return list<string> */
    public function unresolvedReferences(KnowledgeDocumentRecord $document): array
    {
        $unresolved = [];

        foreach ($this->references($document) as $reference) {
            if (! $this->resolveTarget($document, $reference) instanceof KnowledgeDocumentRecord) {
                $unresolved[] = $reference;
            }
        }

        return array_values(array_unique($unresolved));
    }

    /** @return list<string> */
    protected function references(KnowledgeDocumentRecord $document): array
    {
        $references = [];
        $metadata = $document->metadata;

        foreach ($this->metadataKeys() as $key) {
            $values = is_array($metadata[$key] ?? null) ? $metadata[$key] : [];

            foreach ($values as $value) {
                if (is_string($value) && $this->isSupportedReference($value)) {
                    $references[] = trim($value);
                }
            }
        }

        return array_values(array_unique($references));
    }

    abstract public function identifier(): string;

    /** @return list<string> */
    abstract protected function metadataKeys(): array;

    abstract protected function isSupportedReference(string $reference): bool;

    abstract protected function resolveTarget(KnowledgeDocumentRecord $document, string $reference): ?KnowledgeDocumentRecord;

    abstract protected function relationshipType(KnowledgeDocumentRecord $source, KnowledgeDocumentRecord $target, string $reference): string;
}
