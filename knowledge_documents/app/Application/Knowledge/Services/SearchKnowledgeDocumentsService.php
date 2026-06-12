<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;

final readonly class SearchKnowledgeDocumentsService
{
    public function __construct(private KnowledgeDocumentRepositoryInterface $documents) {}

    /**
     * @return list<RankedKnowledgeDocumentData>
     */
    public function fullText(string $query, int $limit): array
    {
        return array_map(
            static fn (array $result): RankedKnowledgeDocumentData => new RankedKnowledgeDocumentData(
                document: KnowledgeDocumentData::fromRecord($result['record']),
                score: $result['score'],
            ),
            $this->documents->fullTextSearch($query, $limit),
        );
    }
}
