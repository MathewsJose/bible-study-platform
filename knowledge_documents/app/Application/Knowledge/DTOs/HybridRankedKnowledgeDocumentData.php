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
            'id' => $this->document->id,
            'source_type' => $this->document->sourceType,
            'source_name' => $this->document->sourceName,
            'tradition' => $this->document->tradition,
            'reference' => $this->document->reference,
            'title' => $this->document->title,
            'content' => $this->document->content,
            'vector_score' => $this->scoreBreakdown['vector'] ?? $this->scoreBreakdown['semantic'] ?? 0.0,
            'lexical_score' => $this->scoreBreakdown['lexical'] ?? $this->scoreBreakdown['full_text'] ?? 0.0,
            'combined_score' => $this->score,
        ];
    }
}
