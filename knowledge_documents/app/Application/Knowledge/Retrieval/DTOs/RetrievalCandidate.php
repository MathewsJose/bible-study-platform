<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\DTOs;

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;

final readonly class RetrievalCandidate
{
    /**
     * @param  array<string, float>  $scoreBreakdown
     * @param  list<string>  $stages
     * @param  list<string>  $explanations
     * @param  list<string>  $relationshipPath
     */
    public function __construct(
        public KnowledgeDocumentData $document,
        public float $score,
        public array $scoreBreakdown,
        public array $stages,
        public array $explanations = [],
        public array $relationshipPath = [],
    ) {}

    public function withScore(float $score, string $stage, float $stageScore, string $explanation): self
    {
        return new self(
            document: $this->document,
            score: round($score, 6),
            scoreBreakdown: array_merge($this->scoreBreakdown, [$stage => round($stageScore, 6), 'combined' => round($score, 6)]),
            stages: array_values(array_unique([...$this->stages, $stage])),
            explanations: [...$this->explanations, $explanation],
            relationshipPath: $this->relationshipPath,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includeExplanation = true): array
    {
        $payload = [
            'id' => $this->document->id,
            'source_type' => $this->document->sourceType,
            'source_name' => $this->document->sourceName,
            'tradition' => $this->document->tradition,
            'reference' => $this->document->reference,
            'title' => $this->document->title,
            'content' => $this->document->content,
            'score' => $this->score,
            'score_breakdown' => $this->scoreBreakdown,
            'stages' => $this->stages,
        ];

        if ($includeExplanation) {
            $payload['explanations'] = $this->explanations;
            $payload['relationship_path'] = $this->relationshipPath;
        }

        return $payload;
    }
}
