<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\ResultFusionStrategyInterface;
use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;

final readonly class WeightedScoreFusionStrategy implements ResultFusionStrategyInterface
{
    /**
     * @param  list<RankedKnowledgeDocumentData>  $vectorResults
     * @param  list<RankedKnowledgeDocumentData>  $lexicalResults
     * @return list<HybridRankedKnowledgeDocumentData>
     */
    public function fuse(array $vectorResults, array $lexicalResults, int $topK, float $minimumScore = 0.0): array
    {
        $vectorWeight = (float) config('retrieval.hybrid.vector_weight', 0.70);
        $lexicalWeight = (float) config('retrieval.hybrid.lexical_weight', 0.30);

        $merged = [];
        $normalizedVectorScores = $this->normalizeScores($vectorResults);
        $normalizedLexicalScores = $this->normalizeScores($lexicalResults);

        foreach ($vectorResults as $index => $result) {
            $merged[$result->document->id] = [
                'document' => $result->document,
                'vector_score' => $normalizedVectorScores[$index] ?? 0.0,
                'lexical_score' => 0.0,
            ];
        }

        foreach ($lexicalResults as $index => $result) {
            $id = $result->document->id;

            if (! isset($merged[$id])) {
                $merged[$id] = [
                    'document' => $result->document,
                    'vector_score' => 0.0,
                    'lexical_score' => 0.0,
                ];
            }

            $merged[$id]['lexical_score'] = $normalizedLexicalScores[$index] ?? 0.0;
        }

        $ranked = array_map(
            static function (array $item) use ($vectorWeight, $lexicalWeight): HybridRankedKnowledgeDocumentData {
                $combinedScore = ($item['vector_score'] * $vectorWeight) + ($item['lexical_score'] * $lexicalWeight);

                /** @var KnowledgeDocumentData $document */
                $document = $item['document'];

                return new HybridRankedKnowledgeDocumentData(
                    document: $document,
                    score: round($combinedScore, 6),
                    scoreBreakdown: [
                        'vector' => round((float) $item['vector_score'], 6),
                        'lexical' => round((float) $item['lexical_score'], 6),
                        'combined' => round($combinedScore, 6),
                    ],
                );
            },
            array_values($merged),
        );

        $ranked = array_values(array_filter(
            $ranked,
            static fn (HybridRankedKnowledgeDocumentData $result): bool => $result->score >= $minimumScore,
        ));

        usort($ranked, static fn (HybridRankedKnowledgeDocumentData $first, HybridRankedKnowledgeDocumentData $second): int => $second->score <=> $first->score);

        return array_slice($ranked, 0, $topK);
    }

    /**
     * @param  list<RankedKnowledgeDocumentData>  $results
     * @return list<float>
     */
    private function normalizeScores(array $results): array
    {
        if ($results === []) {
            return [];
        }

        $scores = array_map(static fn (RankedKnowledgeDocumentData $result): float => max(0.0, $result->score), $results);
        $max = max($scores);

        if ($max <= 0.0) {
            return array_fill(0, count($results), 0.0);
        }

        return array_map(static fn (float $score): float => round($score / $max, 6), $scores);
    }
}
