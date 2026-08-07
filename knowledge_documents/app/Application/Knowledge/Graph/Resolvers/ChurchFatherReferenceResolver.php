<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Resolvers;

use App\Application\Knowledge\Graph\Contracts\ReferenceResolverInterface;
use App\Domain\Knowledge\Enums\RelationshipType;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final class ChurchFatherReferenceResolver extends AbstractMetadataReferenceResolver implements ReferenceResolverInterface
{
    public function identifier(): string
    {
        return 'church_father';
    }

    /** @return list<string> */
    protected function metadataKeys(): array
    {
        return ['church_father_references', 'cross_references'];
    }

    protected function isSupportedReference(string $reference): bool
    {
        return ! str_starts_with($reference, 'CCC ')
            && preg_match('/^[1-3]?\s?[A-Za-z]+\s+\d+:\d+/', $reference) !== 1;
    }

    protected function resolveTarget(KnowledgeDocumentRecord $document, string $reference): ?KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', SourceType::ChurchFather->value)
            ->where('reference', $reference)
            ->first();
    }

    protected function relationshipType(KnowledgeDocumentRecord $source, KnowledgeDocumentRecord $target, string $reference): string
    {
        return RelationshipType::ChurchFatherReference->value;
    }
}
