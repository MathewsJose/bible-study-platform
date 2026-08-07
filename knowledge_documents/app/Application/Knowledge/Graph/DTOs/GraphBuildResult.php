<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\DTOs;

final readonly class GraphBuildResult
{
    /**
     * @param  list<array{document_id: string, reference: string, resolver: string}>  $orphanedReferences
     */
    public function __construct(
        public int $documentsProcessed = 0,
        public int $relationshipsCreated = 0,
        public int $relationshipsUpdated = 0,
        public int $relationshipsRemoved = 0,
        public int $orphanedReferenceCount = 0,
        public array $orphanedReferences = [],
        public float $durationSeconds = 0.0,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            documentsProcessed: $this->documentsProcessed + $other->documentsProcessed,
            relationshipsCreated: $this->relationshipsCreated + $other->relationshipsCreated,
            relationshipsUpdated: $this->relationshipsUpdated + $other->relationshipsUpdated,
            relationshipsRemoved: $this->relationshipsRemoved + $other->relationshipsRemoved,
            orphanedReferenceCount: $this->orphanedReferenceCount + $other->orphanedReferenceCount,
            orphanedReferences: [...$this->orphanedReferences, ...$other->orphanedReferences],
            durationSeconds: $this->durationSeconds + $other->durationSeconds,
        );
    }
}
