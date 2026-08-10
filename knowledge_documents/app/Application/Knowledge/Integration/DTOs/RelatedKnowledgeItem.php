<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Integration\DTOs;

final readonly class RelatedKnowledgeItem
{
    /**
     * @param  array<string, mixed>|null  $document
     */
    public function __construct(
        public string $relationshipId,
        public string $relationshipType,
        public float $confidence,
        public ?array $document,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'relationship_id' => $this->relationshipId,
            'relationship_type' => $this->relationshipType,
            'confidence' => $this->confidence,
            'document' => $this->document,
        ];
    }
}
