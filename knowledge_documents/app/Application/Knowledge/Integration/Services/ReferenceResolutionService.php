<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Integration\Services;

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\Integration\DTOs\KnowledgeDocumentSummary;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class ReferenceResolutionService
{
    public function resolve(string $reference): ?KnowledgeDocumentSummary
    {
        $record = KnowledgeDocumentRecord::query()
            ->whereRaw('lower(reference) = lower(?)', [$reference])
            ->first();

        if (! $record instanceof KnowledgeDocumentRecord) {
            return null;
        }

        return KnowledgeDocumentSummary::fromDocument(KnowledgeDocumentData::fromRecord($record));
    }
}
