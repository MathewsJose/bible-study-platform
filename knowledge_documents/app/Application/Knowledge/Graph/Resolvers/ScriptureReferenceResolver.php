<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Resolvers;

use App\Application\Knowledge\Graph\Contracts\ReferenceResolverInterface;
use App\Domain\Knowledge\Enums\RelationshipType;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final class ScriptureReferenceResolver extends AbstractMetadataReferenceResolver implements ReferenceResolverInterface
{
    public function identifier(): string
    {
        return 'scripture';
    }

    /** @return list<string> */
    protected function metadataKeys(): array
    {
        return ['scripture_references', 'cross_references'];
    }

    protected function isSupportedReference(string $reference): bool
    {
        return preg_match('/^[1-3]?\s?[A-Za-z]+\s+\d+:\d+(?:[-–]\d+)?$/', trim($reference)) === 1;
    }

    protected function resolveTarget(KnowledgeDocumentRecord $document, string $reference): ?KnowledgeDocumentRecord
    {
        return KnowledgeDocumentRecord::query()
            ->whereIn('source_type', [SourceType::BibleVerse->value, SourceType::BibleChapter->value])
            ->where('reference', $reference)
            ->first();
    }

    protected function relationshipType(KnowledgeDocumentRecord $source, KnowledgeDocumentRecord $target, string $reference): string
    {
        if ($source->source_type === SourceType::BibleVerse->value && $target->source_type === SourceType::BibleVerse->value) {
            return RelationshipType::RelatedVerse->value;
        }

        if ($source->source_type === SourceType::ChurchFather->value) {
            return RelationshipType::CommentsOn->value;
        }

        return RelationshipType::ScriptureReference->value;
    }
}
