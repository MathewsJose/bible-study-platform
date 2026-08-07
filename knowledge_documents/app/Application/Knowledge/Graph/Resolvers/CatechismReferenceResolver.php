<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Resolvers;

use App\Application\Knowledge\Graph\Contracts\ReferenceResolverInterface;
use App\Domain\Knowledge\Enums\RelationshipType;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final class CatechismReferenceResolver extends AbstractMetadataReferenceResolver implements ReferenceResolverInterface
{
    public function identifier(): string
    {
        return 'catechism';
    }

    /** @return list<string> */
    protected function metadataKeys(): array
    {
        return ['internal_references', 'catechism_references', 'cross_references'];
    }

    protected function isSupportedReference(string $reference): bool
    {
        return preg_match('/^CCC\s+\d+$/', trim($reference)) === 1;
    }

    protected function resolveTarget(KnowledgeDocumentRecord $document, string $reference): ?KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', SourceType::Catechism->value)
            ->where('reference', $reference)
            ->first();
    }

    protected function relationshipType(KnowledgeDocumentRecord $source, KnowledgeDocumentRecord $target, string $reference): string
    {
        return RelationshipType::CatechismReference->value;
    }
}
