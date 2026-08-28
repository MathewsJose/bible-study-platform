<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

final readonly class ScriptureRoutingCandidate
{
    public function __construct(
        public string $id,
        public string $sourceType,
        public string $sourceName,
        public string $reference,
        public string $title,
        public float $score,
        public string $retrievalOrigin,
        public string $routingReason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $rank): array
    {
        return [
            'rank' => $rank,
            'id' => $this->id,
            'source_type' => $this->sourceType,
            'source_name' => $this->sourceName,
            'reference' => $this->reference,
            'title' => $this->title,
            'score' => $this->score,
            'retrieval_origin' => $this->retrievalOrigin,
            'routing_reason' => $this->routingReason,
        ];
    }
}
