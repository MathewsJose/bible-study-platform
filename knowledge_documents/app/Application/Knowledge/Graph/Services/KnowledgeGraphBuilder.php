<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Services;

use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Application\Knowledge\Graph\Contracts\ReferenceResolverInterface;
use App\Application\Knowledge\Graph\DTOs\GraphBuildResult;
use App\Events\Knowledge\GraphUpdated;
use App\Events\Knowledge\RelationshipCreated;
use App\Events\Knowledge\RelationshipRemoved;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final readonly class KnowledgeGraphBuilder
{
    /** @param  list<ReferenceResolverInterface>  $resolvers */
    public function __construct(
        private KnowledgeGraphRepositoryInterface $repository,
        private array $resolvers,
    ) {}

    /** @param  array{document_id?: string, source_type?: string}  $filters */
    public function rebuild(array $filters = []): GraphBuildResult
    {
        $started = microtime(true);
        $result = new GraphBuildResult();
        $documentId = $filters['document_id'] ?? null;
        $sourceType = $filters['source_type'] ?? null;

        $query = KnowledgeDocumentRecord::query()
            ->when($documentId !== null, static fn (Builder $query): Builder => $query->where('id', $documentId))
            ->when($sourceType !== null, static fn (Builder $query): Builder => $query->where('source_type', $sourceType))
            ->orderBy('id');

        foreach ($query->lazyById(100) as $document) {
            $result = $result->merge($this->updateDocument($document));
        }

        $result = new GraphBuildResult(
            documentsProcessed: $result->documentsProcessed,
            relationshipsCreated: $result->relationshipsCreated,
            relationshipsUpdated: $result->relationshipsUpdated,
            relationshipsRemoved: $result->relationshipsRemoved,
            orphanedReferenceCount: $result->orphanedReferenceCount,
            orphanedReferences: $result->orphanedReferences,
            durationSeconds: round(microtime(true) - $started, 4),
        );

        GraphUpdated::dispatch($result);

        Log::info('Knowledge graph rebuilt.', [
            'documents_processed' => $result->documentsProcessed,
            'relationships_created' => $result->relationshipsCreated,
            'relationships_updated' => $result->relationshipsUpdated,
            'relationships_removed' => $result->relationshipsRemoved,
            'orphaned_references' => $result->orphanedReferenceCount,
            'duration_seconds' => $result->durationSeconds,
        ]);

        return $result;
    }

    public function updateDocument(KnowledgeDocumentRecord $document): GraphBuildResult
    {
        $removed = $this->repository->deleteOutgoing($document->id);
        if ($removed > 0) {
            RelationshipRemoved::dispatch($document->id, $removed);
        }

        $created = 0;
        $updated = 0;
        $orphaned = [];

        foreach ($this->resolvers as $resolver) {
            foreach ($resolver->resolve($document) as $relationship) {
                $record = $this->repository->upsert(
                    $relationship->sourceDocumentId,
                    $relationship->targetDocumentId,
                    $relationship->relationshipType,
                    $relationship->confidence,
                    $relationship->provenance,
                    $relationship->metadata,
                );

                if ($record->wasRecentlyCreated) {
                    $created++;
                    RelationshipCreated::dispatch($record->id);
                } else {
                    $updated++;
                }
            }

            foreach ($resolver->unresolvedReferences($document) as $reference) {
                $orphaned[] = [
                    'document_id' => $document->id,
                    'reference' => $reference,
                    'resolver' => $resolver->identifier(),
                ];
            }
        }

        return new GraphBuildResult(
            documentsProcessed: 1,
            relationshipsCreated: $created,
            relationshipsUpdated: $updated,
            relationshipsRemoved: $removed,
            orphanedReferenceCount: count($orphaned),
            orphanedReferences: $orphaned,
        );
    }
}
