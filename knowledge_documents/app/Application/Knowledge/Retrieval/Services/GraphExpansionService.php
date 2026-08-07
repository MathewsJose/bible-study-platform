<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalProfile;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class GraphExpansionService
{
    public function __construct(private KnowledgeGraphRepositoryInterface $graph) {}

    /**
     * @param  list<RetrievalCandidate>  $candidates
     * @return list<RetrievalCandidate>
     */
    public function expand(array $candidates, RetrievalProfile $profile): array
    {
        if ($profile->graphDepth <= 0 || $candidates === []) {
            return [];
        }

        $expanded = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            foreach ($this->graph->traverse($candidate->document->id, $profile->graphDepth, $profile->relationshipTypes, 30) as $relationship) {
                $related = $relationship->source_document_id === $candidate->document->id
                    ? $relationship->targetDocument
                    : $relationship->sourceDocument;

                if (! $related instanceof KnowledgeDocumentRecord || isset($seen[$related->id])) {
                    continue;
                }

                $seen[$related->id] = true;
                $expanded[] = new RetrievalCandidate(
                    document: KnowledgeDocumentData::fromRecord($related),
                    score: max(0.0, 1.0 - (0.15 * $profile->graphDepth)),
                    scoreBreakdown: ['graph' => max(0.0, 1.0 - (0.15 * $profile->graphDepth))],
                    stages: ['graph'],
                    explanations: [
                        "Selected through {$relationship->relationship_type} from {$candidate->document->reference}.",
                    ],
                    relationshipPath: [
                        $candidate->document->reference,
                        $relationship->relationship_type,
                        $related->reference,
                    ],
                );
            }
        }

        return $expanded;
    }
}
