<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

final readonly class ScriptureRoutingResult
{
    /**
     * @param  list<ScriptureRoutingCandidate>  $results
     * @param  array<string, mixed>  $diagnostics
     */
    public function __construct(
        public string $query,
        public string $mode,
        public ScriptureRoutingClassification $classification,
        public array $results,
        public array $diagnostics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'mode' => $this->mode,
            'classification' => $this->classification->toArray(),
            'results' => array_map(
                static fn (ScriptureRoutingCandidate $candidate, int $index): array => $candidate->toArray($index + 1),
                $this->results,
                array_keys($this->results),
            ),
            'diagnostics' => $this->diagnostics,
        ];
    }
}
