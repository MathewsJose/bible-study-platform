<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;

final readonly class SemanticSearchService
{
    public function __construct(
        private EmbeddingProviderInterface $embeddings,
        private KnowledgeDocumentRepositoryInterface $documents,
    ) {}

    /**
     * @return list<RankedKnowledgeDocumentData>
     */
    public function search(string $query, int $limit): array
    {
        $embedding = $this->embeddings->embed($query);

        return array_map(
            static fn (array $result): RankedKnowledgeDocumentData => new RankedKnowledgeDocumentData(
                document: KnowledgeDocumentData::fromRecord($result['record']),
                score: $result['score'],
            ),
            $this->documents->semanticSearch($embedding, $limit),
        );
    }
}
