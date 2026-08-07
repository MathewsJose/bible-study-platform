<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Graph;

use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRelationshipRecord;
use Illuminate\Database\Eloquent\Builder;

final class EloquentKnowledgeGraphRepository implements KnowledgeGraphRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $provenance
     * @param  array<string, mixed>  $metadata
     */
    public function upsert(
        string $sourceDocumentId,
        string $targetDocumentId,
        string $relationshipType,
        float $confidence,
        array $provenance,
        array $metadata,
    ): KnowledgeDocumentRelationshipRecord {
        return KnowledgeDocumentRelationshipRecord::query()->updateOrCreate(
            [
                'source_document_id' => $sourceDocumentId,
                'target_document_id' => $targetDocumentId,
                'relationship_type' => $relationshipType,
            ],
            [
                'confidence' => $confidence,
                'provenance' => $provenance,
                'metadata' => $metadata,
            ],
        );
    }

    public function deleteOutgoing(string $sourceDocumentId): int
    {
        return KnowledgeDocumentRelationshipRecord::query()
            ->where('source_document_id', $sourceDocumentId)
            ->delete();
    }

    /**
     * @param  list<string>  $relationshipTypes
     * @return list<KnowledgeDocumentRelationshipRecord>
     */
    public function relationshipsForDocument(string $documentId, array $relationshipTypes = [], int $limit = 50): array
    {
        return KnowledgeDocumentRelationshipRecord::query()
            ->with(['sourceDocument', 'targetDocument'])
            ->where(static function (Builder $query) use ($documentId): void {
                $query->where('source_document_id', $documentId)
                    ->orWhere('target_document_id', $documentId);
            })
            ->when($relationshipTypes !== [], static fn (Builder $query): Builder => $query->whereIn('relationship_type', $relationshipTypes))
            ->latest()
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @param  list<string>  $relationshipTypes
     * @return list<KnowledgeDocumentRelationshipRecord>
     */
    public function traverse(string $documentId, int $depth = 1, array $relationshipTypes = [], int $limit = 100): array
    {
        $depth = max(1, min($depth, 2));
        $visited = [$documentId => true];
        $seenRelationships = [];
        $frontier = [$documentId];
        $relationships = [];

        for ($level = 1; $level <= $depth && $frontier !== [] && count($relationships) < $limit; $level++) {
            $nextFrontier = [];

            foreach ($frontier as $currentDocumentId) {
                $edges = $this->relationshipsForDocument($currentDocumentId, $relationshipTypes, $limit - count($relationships));

                foreach ($edges as $edge) {
                    if (isset($seenRelationships[$edge->id])) {
                        continue;
                    }

                    $seenRelationships[$edge->id] = true;
                    $relationships[] = $edge;
                    $neighborId = $edge->source_document_id === $currentDocumentId
                        ? $edge->target_document_id
                        : $edge->source_document_id;

                    if (! isset($visited[$neighborId])) {
                        $visited[$neighborId] = true;
                        $nextFrontier[] = $neighborId;
                    }

                    if (count($relationships) >= $limit) {
                        break 2;
                    }
                }
            }

            $frontier = $nextFrontier;
        }

        return $relationships;
    }

    /** @return array<string, int> */
    public function relationshipCounts(): array
    {
        return KnowledgeDocumentRelationshipRecord::query()
            ->select('relationship_type')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('relationship_type')
            ->orderBy('relationship_type')
            ->get()
            ->mapWithKeys(static fn (KnowledgeDocumentRelationshipRecord $record): array => [
                (string) $record->relationship_type => (int) $record->getAttribute('aggregate'),
            ])
            ->all();
    }

    public function totalNodes(): int
    {
        return KnowledgeDocumentRecord::query()->count();
    }

    public function totalEdges(): int
    {
        return KnowledgeDocumentRelationshipRecord::query()->count();
    }

    public function disconnectedNodeCount(): int
    {
        return KnowledgeDocumentRecord::query()
            ->whereDoesntHave('outgoingRelationships')
            ->whereDoesntHave('incomingRelationships')
            ->count();
    }

    public function duplicateRelationshipCount(): int
    {
        return KnowledgeDocumentRelationshipRecord::query()
            ->select('source_document_id', 'target_document_id', 'relationship_type')
            ->selectRaw('count(*) as duplicate_count')
            ->groupBy('source_document_id', 'target_document_id', 'relationship_type')
            ->havingRaw('count(*) > 1')
            ->count();
    }

    public function brokenRelationshipCount(): int
    {
        return KnowledgeDocumentRelationshipRecord::query()
            ->whereDoesntHave('sourceDocument')
            ->orWhereDoesntHave('targetDocument')
            ->count();
    }

    public function averageDegree(): float
    {
        $nodes = $this->totalNodes();

        if ($nodes === 0) {
            return 0.0;
        }

        return round(($this->totalEdges() * 2) / $nodes, 4);
    }

    public function density(): float
    {
        $nodes = $this->totalNodes();

        if ($nodes <= 1) {
            return 0.0;
        }

        return round($this->totalEdges() / ($nodes * ($nodes - 1)), 6);
    }
}
