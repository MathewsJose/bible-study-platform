<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\DTOs\CitationData;
use App\Application\Knowledge\Answering\DTOs\ConfidenceData;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalResult;

final readonly class ConfidenceScorer
{
    /** @param  list<CitationData>  $citations */
    public function score(RetrievalResult $retrieval, array $citations): ConfidenceData
    {
        $retrievalScore = $this->averageRetrievalScore($citations);
        $citationScore = min(1.0, count($citations) / 3);
        $authorityScore = $this->authorityScore($citations);
        $graphScore = $this->graphScore($retrieval);

        $score = round(min(1.0, ($retrievalScore * 0.45) + ($citationScore * 0.25) + ($authorityScore * 0.20) + ($graphScore * 0.10)), 4);

        return new ConfidenceData(
            score: $score,
            explanations: [
                'Confidence is deterministic and based on retrieval quality, citation coverage, source authority, and graph support.',
                "Retrieved citations: ".count($citations).'.',
            ],
            signals: [
                'retrieval_score' => round($retrievalScore, 4),
                'citation_coverage' => round($citationScore, 4),
                'source_authority' => round($authorityScore, 4),
                'graph_support' => round($graphScore, 4),
            ],
        );
    }

    /** @param  list<CitationData>  $citations */
    private function averageRetrievalScore(array $citations): float
    {
        if ($citations === []) {
            return 0.0;
        }

        return array_sum(array_map(static fn (CitationData $citation): float => min(1.0, $citation->retrievalScore), $citations)) / count($citations);
    }

    /** @param  list<CitationData>  $citations */
    private function authorityScore(array $citations): float
    {
        if ($citations === []) {
            return 0.0;
        }

        $scores = array_map(
            static fn (CitationData $citation): float => match ($citation->sourceType) {
                'bible_verse', 'catechism', 'church_father' => 1.0,
                'bible_chapter' => 0.85,
                default => 0.35,
            },
            $citations,
        );

        return array_sum($scores) / count($scores);
    }

    private function graphScore(RetrievalResult $retrieval): float
    {
        $graphResults = (float) ($retrieval->diagnostics->metrics['graph_results'] ?? 0);

        return min(1.0, $graphResults / 2);
    }
}
