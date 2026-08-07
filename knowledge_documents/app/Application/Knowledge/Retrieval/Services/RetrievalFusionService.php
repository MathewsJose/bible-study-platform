<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalProfile;

final readonly class RetrievalFusionService
{
    /**
     * @param  list<RetrievalCandidate>  $candidates
     * @return list<RetrievalCandidate>
     */
    public function fuse(array $candidates, RetrievalProfile $profile): array
    {
        $merged = [];

        foreach ($candidates as $candidate) {
            $id = $candidate->document->id;

            if (! isset($merged[$id])) {
                $merged[$id] = [
                    'document' => $candidate->document,
                    'scores' => [],
                    'stages' => [],
                    'explanations' => [],
                    'relationship_path' => [],
                ];
            }

            foreach ($candidate->scoreBreakdown as $stage => $score) {
                $merged[$id]['scores'][$stage] = max((float) ($merged[$id]['scores'][$stage] ?? 0.0), $score);
            }

            $merged[$id]['stages'] = array_values(array_unique([...$merged[$id]['stages'], ...$candidate->stages]));
            $merged[$id]['explanations'] = array_values(array_unique([...$merged[$id]['explanations'], ...$candidate->explanations]));
            $merged[$id]['relationship_path'] = $merged[$id]['relationship_path'] ?: $candidate->relationshipPath;
        }

        $fused = [];
        foreach ($merged as $item) {
            $score = 0.0;
            foreach ($item['scores'] as $stage => $stageScore) {
                $score += ((float) $stageScore) * $profile->weight((string) $stage);
            }

            $fused[] = new RetrievalCandidate(
                document: $item['document'],
                score: round($score, 6),
                scoreBreakdown: array_merge($item['scores'], ['combined' => round($score, 6)]),
                stages: $item['stages'],
                explanations: $item['explanations'],
                relationshipPath: $item['relationship_path'],
            );
        }

        usort($fused, static fn (RetrievalCandidate $first, RetrievalCandidate $second): int => $second->score <=> $first->score);

        return $fused;
    }
}
