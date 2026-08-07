<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\Retrieval\DTOs\AnalyzedQuery;
use App\Application\Knowledge\Retrieval\DTOs\QueryExpansion;

final readonly class QueryExpansionService
{
    public function expand(AnalyzedQuery $query): QueryExpansion
    {
        $terms = [];
        $references = $query->references;
        $explanations = [];
        $expansions = (array) config('retrieval.expansions', []);

        foreach ($query->topics as $topic) {
            $definition = (array) ($expansions[$topic] ?? []);
            $topicTerms = array_values(array_filter((array) ($definition['terms'] ?? []), 'is_string'));
            $topicReferences = array_values(array_filter((array) ($definition['references'] ?? []), 'is_string'));

            if ($topicTerms !== [] || $topicReferences !== []) {
                $terms = [...$terms, ...$topicTerms];
                $references = [...$references, ...$topicReferences];
                $explanations[] = "Expanded topic [{$topic}] using configured Catholic knowledge aliases.";
            }
        }

        return new QueryExpansion(
            terms: array_values(array_unique($terms)),
            references: array_values(array_unique($references)),
            explanations: $explanations,
        );
    }
}
