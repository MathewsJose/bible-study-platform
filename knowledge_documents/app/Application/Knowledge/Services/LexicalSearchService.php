<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;

final readonly class LexicalSearchService
{
    public function __construct(private KnowledgeDocumentRepositoryInterface $documents) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<RankedKnowledgeDocumentData>
     */
    public function search(string $query, int $limit, array $filters = []): array
    {
        return array_map(
            static fn (array $result): RankedKnowledgeDocumentData => new RankedKnowledgeDocumentData(
                document: KnowledgeDocumentData::fromRecord($result['record']),
                score: $result['score'],
            ),
            $this->documents->fullTextSearch($query, $limit, $filters),
        );
    }
}
