<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

final readonly class QueryExpansionResult
{
    /**
     * @param  list<string>  $terms
     * @param  list<string>  $reasons
     * @param  list<string>  $profiles
     */
    public function __construct(
        public string $originalQuery,
        public string $mode,
        public string $expandedQuery,
        public array $terms,
        public array $reasons,
        public array $profiles,
        public string $configVersion,
        public string $configFingerprint,
        public float $queryDriftScore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original_query' => $this->originalQuery,
            'mode' => $this->mode,
            'expanded_query' => $this->expandedQuery,
            'terms' => $this->terms,
            'reasons' => $this->reasons,
            'profiles' => $this->profiles,
            'config_version' => $this->configVersion,
            'config_fingerprint' => $this->configFingerprint,
            'query_drift_score' => $this->queryDriftScore,
        ];
    }
}
