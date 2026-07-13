<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use Illuminate\Support\Facades\Config;

final readonly class HybridSearchService
{
    public function __construct(
        private SearchKnowledgeDocumentsService $fullTextSearch,
        private SemanticSearchService $semanticSearch,
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
        // Fetch more results to improve hybrid quality
        $fetchLimit = $limit * 3;

        $fullTextResults = $this->fullTextSearch->fullText($query, $fetchLimit, $filters);
        
        $semanticPaginator = $this->semanticSearch->search($query, $fetchLimit, $threshold, 1, $filters);
        $semanticResults = $semanticPaginator->items();

        $weights = Config::get('knowledge.hybrid_search.weights');
        $prioritySources = Config::get('knowledge.hybrid_search.priority_sources', []);

        $merged = [];

        foreach ($fullTextResults as $result) {
            $id = $result->document->id;
            $merged[$id] = [
                'document' => $result->document,
                'full_text_score' => $result->score,
                'semantic_score' => 0.0,
            ];
        }

        foreach ($semanticResults as $result) {
            $id = $result->document->id;
            if (isset($merged[$id])) {
                $merged[$id]['semantic_score'] = $result->score;
            } else {
                $merged[$id] = [
                    'document' => $result->document,
                    'full_text_score' => 0.0,
                    'semantic_score' => $result->score,
                ];
            }
        }

        $ranked = [];
        foreach ($merged as $item) {
            $priorityBoost = in_array($item['document']->sourceName, $prioritySources, true) ? 1.0 : 0.0;
            
            $semanticContribution = $item['semantic_score'] * $weights['semantic'];
            $fullTextContribution = $item['full_text_score'] * $weights['full_text'];
            $priorityContribution = $priorityBoost * $weights['source_priority'];

            $score = $semanticContribution + $fullTextContribution + $priorityContribution;
            
            $ranked[] = new HybridRankedKnowledgeDocumentData(
                document: $item['document'],
                score: round((float) $score, 6),
                scoreBreakdown: [
                    'semantic' => round((float) $item['semantic_score'], 6),
                    'full_text' => round((float) $item['full_text_score'], 6),
                    'source_priority' => round((float) $priorityBoost, 6),
                ]
            );
        }

        usort($ranked, static fn (HybridRankedKnowledgeDocumentData $a, HybridRankedKnowledgeDocumentData $b): int => $b->score <=> $a->score);

        return array_slice($ranked, 0, $limit);
    }
}
