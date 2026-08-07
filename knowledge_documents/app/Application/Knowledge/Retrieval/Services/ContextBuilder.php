<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalContextDocument;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalProfile;

final readonly class ContextBuilder
{
    /**
     * @param  list<RetrievalCandidate>  $candidates
     * @return list<RetrievalContextDocument>
     */
    public function build(array $candidates, RetrievalProfile $profile): array
    {
        $context = [];
        $seen = [];
        $usedTokens = 0;

        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate->document->id])) {
                continue;
            }

            $estimatedTokens = $this->estimateTokens($candidate->document->content);
            if ($context !== [] && $usedTokens + $estimatedTokens > $profile->tokenBudget) {
                continue;
            }

            $seen[$candidate->document->id] = true;
            $usedTokens += $estimatedTokens;
            $context[] = new RetrievalContextDocument(
                candidate: $candidate,
                estimatedTokens: $estimatedTokens,
                provenance: [
                    'source_type' => $candidate->document->sourceType,
                    'source_name' => $candidate->document->sourceName,
                    'reference' => $candidate->document->reference,
                    'stages' => $candidate->stages,
                ],
            );

            if (count($context) >= $profile->contextLimit) {
                break;
            }
        }

        return $context;
    }

    private function estimateTokens(string $content): int
    {
        return max(1, (int) ceil(str_word_count($content) * 1.35));
    }
}
