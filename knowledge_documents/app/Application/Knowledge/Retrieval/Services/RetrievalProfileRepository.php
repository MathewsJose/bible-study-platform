<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\Retrieval\DTOs\RetrievalProfile;

final readonly class RetrievalProfileRepository
{
    public function resolve(?string $profile = null): RetrievalProfile
    {
        $identifier = $profile ?: (string) config('retrieval.engine.default_profile', 'ai_answer');
        $profiles = (array) config('retrieval.profiles', []);
        $configuration = (array) ($profiles[$identifier] ?? $profiles['ai_answer'] ?? []);

        return new RetrievalProfile(
            identifier: $identifier,
            topK: (int) ($configuration['top_k'] ?? config('retrieval.engine.default_top_k', 10)),
            contextLimit: (int) ($configuration['context_limit'] ?? config('retrieval.engine.default_context_limit', 8)),
            tokenBudget: (int) ($configuration['token_budget'] ?? config('retrieval.engine.default_token_budget', 2500)),
            useVector: (bool) ($configuration['use_vector'] ?? true),
            useLexical: (bool) ($configuration['use_lexical'] ?? true),
            useExpansion: (bool) ($configuration['use_expansion'] ?? true),
            graphDepth: (int) ($configuration['graph_depth'] ?? 1),
            relationshipTypes: array_values(array_filter((array) ($configuration['relationship_types'] ?? []), 'is_string')),
            weights: array_map('floatval', (array) ($configuration['weights'] ?? [])),
            includeExplanations: (bool) ($configuration['include_explanations'] ?? true),
        );
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return array_keys((array) config('retrieval.profiles', []));
    }
}
