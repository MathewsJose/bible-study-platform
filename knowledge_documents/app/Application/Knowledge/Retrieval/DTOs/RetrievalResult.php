<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\DTOs;

final readonly class RetrievalResult
{
    /**
     * @param  list<RetrievalContextDocument>  $context
     */
    public function __construct(
        public AnalyzedQuery $query,
        public QueryExpansion $expansion,
        public RetrievalProfile $profile,
        public array $context,
        public RetrievalDiagnostics $diagnostics,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'query' => $this->query->toArray(),
            'profile' => $this->profile->identifier,
            'expansion' => $this->expansion->toArray(),
            'context' => array_map(
                fn (RetrievalContextDocument $document): array => $document->toArray($this->profile->includeExplanations),
                $this->context,
            ),
            'diagnostics' => $this->diagnostics->toArray(),
        ];
    }
}
