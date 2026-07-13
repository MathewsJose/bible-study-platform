<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class HybridRankedKnowledgeDocumentData
{
    /**
     * @param  array<string, float>  $scoreBreakdown
     */
    public function __construct(
        public KnowledgeDocumentData $document,
        public float $score,
        public array $scoreBreakdown,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'document' => $this->document->toArray(),
            'score' => $this->score,
            'score_breakdown' => $this->scoreBreakdown,
        ];
    }
}
