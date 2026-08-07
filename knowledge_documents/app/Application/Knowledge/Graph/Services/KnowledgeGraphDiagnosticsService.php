<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Services;

use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Application\Knowledge\Graph\DTOs\GraphDiagnostics;

final readonly class KnowledgeGraphDiagnosticsService
{
    public function __construct(private KnowledgeGraphRepositoryInterface $repository) {}

    public function diagnostics(): GraphDiagnostics
    {
        return new GraphDiagnostics(
            totalNodes: $this->repository->totalNodes(),
            totalEdges: $this->repository->totalEdges(),
            relationshipCounts: $this->repository->relationshipCounts(),
            disconnectedNodes: $this->repository->disconnectedNodeCount(),
            duplicateRelationships: $this->repository->duplicateRelationshipCount(),
            brokenRelationships: $this->repository->brokenRelationshipCount(),
            averageDegree: $this->repository->averageDegree(),
            density: $this->repository->density(),
        );
    }
}
