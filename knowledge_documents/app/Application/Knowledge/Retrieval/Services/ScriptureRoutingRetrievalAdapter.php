<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\Retrieval\DTOs\AnalyzedQuery;
use App\Application\Knowledge\Retrieval\DTOs\QueryExpansion;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalDiagnostics;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalProfile;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalResult;
use App\Application\Knowledge\Retrieval\Experiments\ScriptureRoutingSearchService;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use RuntimeException;

final readonly class ScriptureRoutingRetrievalAdapter
{
    public function __construct(
        private QueryAnalyzer $analyzer,
        private ContextBuilder $context,
        private ScriptureRoutingSearchService $routing,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function retrieve(string $query, RetrievalProfile $profile, array $filters = []): RetrievalResult
    {
        $startedAt = microtime(true);
        $routed = $this->routing->search(
            query: $query,
            mode: (string) config('retrieval.scripture_router.mode', 'hybrid_router'),
            limit: $profile->topK,
            sourceName: isset($filters['source_name']) ? (string) $filters['source_name'] : null,
        );

        $ranked = $routed->toArray()['results'];

        if ($ranked === []) {
            throw new RuntimeException('Experimental Scripture router returned no candidates.');
        }

        $records = KnowledgeDocumentRecord::query()
            ->whereIn('id', array_values(array_map(static fn (array $row): string => (string) $row['id'], $ranked)))
            ->get()
            ->keyBy('id');

        $candidates = [];

        foreach ($ranked as $row) {
            $record = $records->get((string) $row['id']);

            if (! $record instanceof KnowledgeDocumentRecord) {
                continue;
            }

            $origin = (string) $row['retrieval_origin'];
            $candidates[] = new RetrievalCandidate(
                document: KnowledgeDocumentData::fromRecord($record),
                score: (float) $row['score'],
                scoreBreakdown: [
                    $origin => (float) $row['score'],
                    'combined' => (float) $row['score'],
                ],
                stages: ['scripture_router', $origin],
                explanations: [(string) $row['routing_reason']],
            );
        }

        if ($candidates === []) {
            throw new RuntimeException('Experimental Scripture router candidates could not be resolved to documents.');
        }

        $context = $this->context->build($candidates, $profile);

        return new RetrievalResult(
            query: $this->analyzer->analyze($query),
            expansion: new QueryExpansion(references: $routed->classification->references),
            profile: $profile,
            context: $context,
            diagnostics: new RetrievalDiagnostics([
                'scripture_router_total' => (int) round((microtime(true) - $startedAt) * 1000),
            ], [
                'scripture_router_enabled' => 1,
                'scripture_router_fallback' => 0,
                'scripture_router_route' => $routed->classification->route,
                'scripture_router_references' => count($routed->classification->references),
                'context_documents' => count($context),
                'profile' => $profile->identifier,
            ]),
        );
    }
}
