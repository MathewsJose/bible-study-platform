<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\DTOs;

final readonly class RetrievalContextDocument
{
    /**
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public RetrievalCandidate $candidate,
        public int $estimatedTokens,
        public array $provenance,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(bool $includeExplanation = true): array
    {
        return array_merge($this->candidate->toArray($includeExplanation), [
            'estimated_tokens' => $this->estimatedTokens,
            'provenance' => $this->provenance,
        ]);
    }
}
