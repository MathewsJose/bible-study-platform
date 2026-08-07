<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\DTOs;

final readonly class QueryExpansion
{
    /**
     * @param  list<string>  $terms
     * @param  list<string>  $references
     * @param  list<string>  $explanations
     */
    public function __construct(
        public array $terms = [],
        public array $references = [],
        public array $explanations = [],
    ) {}

    public function expandedQuery(string $query): string
    {
        $parts = array_values(array_unique(array_filter([
            $query,
            ...$this->terms,
            ...$this->references,
        ])));

        return implode(' ', $parts);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'terms' => $this->terms,
            'references' => $this->references,
            'explanations' => $this->explanations,
        ];
    }
}
