<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\DTOs;

final readonly class GraphDiagnostics
{
    /**
     * @param  array<string, int>  $relationshipCounts
     */
    public function __construct(
        public int $totalNodes,
        public int $totalEdges,
        public array $relationshipCounts,
        public int $disconnectedNodes,
        public int $duplicateRelationships,
        public int $brokenRelationships,
        public float $averageDegree,
        public float $density,
    ) {}
}
