<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class RankedKnowledgeDocumentData
{
    public function __construct(
        public KnowledgeDocumentData $document,
        public float $score,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'document' => $this->document->toArray(),
            'score' => $this->score,
        ];
    }
}
