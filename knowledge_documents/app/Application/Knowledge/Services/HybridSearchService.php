<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\ResultFusionStrategyInterface;
use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use Illuminate\Support\Facades\Log;

final readonly class HybridSearchService
{
    public function __construct(
        private LexicalSearchService $lexicalSearch,
        private SemanticSearchService $semanticSearch,
        private ResultFusionStrategyInterface $fusion,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<HybridRankedKnowledgeDocumentData>
     */
    public function search(
        string $query,
        int $limit = 10,
        float $threshold = 0.0,
        array $filters = []
    ): array {
        $fetchLimit = $limit * max(1, (int) config('retrieval.hybrid.fetch_multiplier', 3));
        $startedAt = microtime(true);

        $semanticStartedAt = microtime(true);
        $semanticResults = $this->semanticSearch->search($query, $fetchLimit, 0.0, $filters);
        $semanticMs = (int) round((microtime(true) - $semanticStartedAt) * 1000);

        $lexicalStartedAt = microtime(true);
        $lexicalResults = $this->lexicalSearch->search($query, $fetchLimit, $filters);
        $lexicalMs = (int) round((microtime(true) - $lexicalStartedAt) * 1000);

        $fusionStartedAt = microtime(true);
        $results = $this->fusion->fuse($semanticResults, $lexicalResults, $limit, $threshold);
        $fusionMs = (int) round((microtime(true) - $fusionStartedAt) * 1000);

        Log::info('Hybrid retrieval completed.', [
            'top_k' => $limit,
            'minimum_score' => $threshold,
            'semantic_results' => count($semanticResults),
            'lexical_results' => count($lexicalResults),
            'hybrid_results' => count($results),
            'semantic_ms' => $semanticMs,
            'lexical_ms' => $lexicalMs,
            'fusion_ms' => $fusionMs,
            'total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $results;
    }
}
