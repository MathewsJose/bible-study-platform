<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\DTOs;

final readonly class ResolvedRelationship
{
    /**
     * @param  array<string, mixed>  $provenance
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceDocumentId,
        public string $targetDocumentId,
        public string $relationshipType,
        public float $confidence = 1.0,
        public array $provenance = [],
        public array $metadata = [],
    ) {}
}
