<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Exceptions\EmbeddingProviderUnavailableException;
use Throwable;

final readonly class SemanticSearchService
{
    public function __construct(
        private EmbeddingProviderInterface $embeddings,
        private EmbeddingRepositoryInterface $documents,
        private EmbeddingVectorValidator $vectors,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<RankedKnowledgeDocumentData>
     */
    public function search(string $query, int $topK, float $threshold, array $filters = []): array
    {
        try {
            $embedding = $this->embeddings->embed($query);
            $this->vectors->validate(array_values($embedding));
            $results = $this->documents->semanticSearch($embedding, $topK, $threshold, $filters);
        } catch (Throwable $exception) {
            throw EmbeddingProviderUnavailableException::forSearch($exception);
        }

        return array_map(
            static fn (array $result): RankedKnowledgeDocumentData => new RankedKnowledgeDocumentData(
                document: KnowledgeDocumentData::fromRecord($result['record']),
                score: $result['score'],
            ),
            $results,
        );
    }
}
