<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\Retrieval\DTOs\AnalyzedQuery;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalProfile;

final readonly class RerankerService
{
    /**
     * @param  list<RetrievalCandidate>  $candidates
     * @return list<RetrievalCandidate>
     */
    public function rerank(array $candidates, AnalyzedQuery $query, RetrievalProfile $profile): array
    {
        $reranked = array_map(
            function (RetrievalCandidate $candidate) use ($query, $profile): RetrievalCandidate {
                $metadataScore = $this->metadataScore($candidate, $query);
                $authorityScore = $this->authorityScore($candidate);
                $score = $candidate->score
                    + ($metadataScore * $profile->weight('metadata'))
                    + ($authorityScore * $profile->weight('authority'));

                $explanations = $candidate->explanations;
                if ($metadataScore > 0.0) {
                    $explanations[] = 'Boosted because document metadata matches analyzed query topics or references.';
                }

                if ($authorityScore > 0.0) {
                    $explanations[] = 'Boosted because source type is authoritative for Catholic retrieval.';
                }

                return new RetrievalCandidate(
                    document: $candidate->document,
                    score: round($score, 6),
                    scoreBreakdown: array_merge($candidate->scoreBreakdown, [
                        'metadata' => round($metadataScore, 6),
                        'authority' => round($authorityScore, 6),
                        'reranked' => round($score, 6),
                    ]),
                    stages: array_values(array_unique([...$candidate->stages, 'rerank'])),
                    explanations: $explanations,
                    relationshipPath: $candidate->relationshipPath,
                );
            },
            $candidates,
        );

        usort($reranked, static fn (RetrievalCandidate $first, RetrievalCandidate $second): int => $second->score <=> $first->score);

        return $reranked;
    }

    private function metadataScore(RetrievalCandidate $candidate, AnalyzedQuery $query): float
    {
        $metadata = $candidate->document->metadata;
        $score = 0.0;

        foreach ($query->topics as $topic) {
            if (in_array($topic, (array) ($metadata['topics'] ?? []), true) || (string) ($metadata['category'] ?? '') === $topic) {
                $score = max($score, 1.0);
            }
        }

        foreach ($query->references as $reference) {
            if ($candidate->document->reference === $reference) {
                $score = max($score, 1.0);
            }
        }

        return $score;
    }

    private function authorityScore(RetrievalCandidate $candidate): float
    {
        return match ($candidate->document->sourceType) {
            'bible_verse', 'catechism', 'church_father' => 1.0,
            'bible_chapter' => 0.85,
            default => 0.25,
        };
    }
}
