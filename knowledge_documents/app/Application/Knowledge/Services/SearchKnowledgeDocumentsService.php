<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;

final readonly class SearchKnowledgeDocumentsService
{
    public function __construct(private LexicalSearchService $lexicalSearch) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<RankedKnowledgeDocumentData>
     */
    public function fullText(string $query, int $limit, array $filters = []): array
    {
        return $this->lexicalSearch->search($query, $limit, $filters);
    }
}
