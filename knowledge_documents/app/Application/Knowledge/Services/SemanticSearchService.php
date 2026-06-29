<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Exceptions\EmbeddingProviderUnavailableException;
use Illuminate\Pagination\LengthAwarePaginator;
use Throwable;

final readonly class SemanticSearchService
{
    public function __construct(
        private EmbeddingProviderInterface $embeddings,
        private KnowledgeDocumentRepositoryInterface $documents,
    ) {}

    /**
     * @return LengthAwarePaginator<int, RankedKnowledgeDocumentData>
     */
    public function search(string $query, int $limit, float $threshold, int $page): LengthAwarePaginator
    {
        try {
            $embedding = $this->embeddings->embed($query);
        } catch (Throwable $exception) {
            throw EmbeddingProviderUnavailableException::forSearch($exception);
        }

        /** @var LengthAwarePaginator<int, RankedKnowledgeDocumentData> $results */
        $results = $this->documents
            ->semanticSearch($embedding, $limit, $threshold, $page)
            ->through(static fn (array $result): RankedKnowledgeDocumentData => new RankedKnowledgeDocumentData(
                document: KnowledgeDocumentData::fromRecord($result['record']),
                score: $result['score'],
            ));

        return $results;
    }
}
