<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\DTOs;

final readonly class RetrievalProfile
{
    /**
     * @param  array<string, float>  $weights
     * @param  list<string>  $relationshipTypes
     */
    public function __construct(
        public string $identifier,
        public int $topK,
        public int $contextLimit,
        public int $tokenBudget,
        public bool $useVector,
        public bool $useLexical,
        public bool $useExpansion,
        public int $graphDepth,
        public array $relationshipTypes,
        public array $weights,
        public bool $includeExplanations,
    ) {}

    public function weight(string $stage): float
    {
        return $this->weights[$stage] ?? 0.0;
    }
}
