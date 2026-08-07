<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Exceptions\EmbeddingProviderUnavailableException;
use App\Application\Knowledge\Retrieval\DTOs\QueryExpansion;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalDiagnostics;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalResult;
use App\Application\Knowledge\Services\LexicalSearchService;
use App\Application\Knowledge\Services\SemanticSearchService;
use Illuminate\Support\Facades\Log;

final readonly class RetrievalEngine
{
    public function __construct(
        private RetrievalProfileRepository $profiles,
        private QueryAnalyzer $analyzer,
        private QueryExpansionService $expander,
        private MetadataFilterService $metadata,
        private SemanticSearchService $semantic,
        private LexicalSearchService $lexical,
        private GraphExpansionService $graph,
        private RetrievalFusionService $fusion,
        private RerankerService $reranker,
        private ContextBuilder $context,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function retrieve(
        string $query,
        ?string $profile = null,
        array $filters = [],
        ?int $topK = null,
        ?int $contextLimit = null,
        bool $includeExplanations = true,
    ): RetrievalResult {
        $startedAt = microtime(true);
        $timings = [];
        $metrics = [];

        $retrievalProfile = $this->profiles->resolve($profile);
        if ($topK !== null || $contextLimit !== null || ! $includeExplanations) {
            $retrievalProfile = new \App\Application\Knowledge\Retrieval\DTOs\RetrievalProfile(
                identifier: $retrievalProfile->identifier,
                topK: $topK ?? $retrievalProfile->topK,
                contextLimit: $contextLimit ?? $retrievalProfile->contextLimit,
                tokenBudget: $retrievalProfile->tokenBudget,
                useVector: $retrievalProfile->useVector,
                useLexical: $retrievalProfile->useLexical,
                useExpansion: $retrievalProfile->useExpansion,
                graphDepth: $retrievalProfile->graphDepth,
                relationshipTypes: $retrievalProfile->relationshipTypes,
                weights: $retrievalProfile->weights,
                includeExplanations: $includeExplanations && $retrievalProfile->includeExplanations,
            );
        }

        $stageStartedAt = microtime(true);
        $analyzed = $this->analyzer->analyze($query);
        $timings['query_analysis'] = $this->elapsedMs($stageStartedAt);

        $stageStartedAt = microtime(true);
        $expansion = $retrievalProfile->useExpansion ? $this->expander->expand($analyzed) : new QueryExpansion();
        $expandedQuery = $expansion->expandedQuery($query);
        $timings['query_expansion'] = $this->elapsedMs($stageStartedAt);

        $filters = $this->metadata->normalize($filters);
        $fetchLimit = max($retrievalProfile->topK, $retrievalProfile->contextLimit) * max(1, (int) config('retrieval.hybrid.fetch_multiplier', 3));
        $candidates = [];

        if ($retrievalProfile->useVector) {
            $stageStartedAt = microtime(true);
            try {
                $semanticResults = $this->semantic->search($expandedQuery, $fetchLimit, 0.0, $filters);
                $candidates = [...$candidates, ...$this->candidates($semanticResults, 'vector', 'Selected by semantic vector similarity.')];
                $metrics['vector_results'] = count($semanticResults);
            } catch (EmbeddingProviderUnavailableException $exception) {
                $metrics['vector_unavailable'] = 1;
                Log::warning('Retrieval engine vector stage unavailable.', ['exception' => $exception]);
            }
            $timings['vector_retrieval'] = $this->elapsedMs($stageStartedAt);
        }

        if ($retrievalProfile->useLexical) {
            $stageStartedAt = microtime(true);
            $lexicalResults = $this->lexical->search($expandedQuery, $fetchLimit, $filters);
            $candidates = [...$candidates, ...$this->candidates($lexicalResults, 'lexical', 'Selected by lexical full-text search.')];
            $metrics['lexical_results'] = count($lexicalResults);
            $timings['lexical_retrieval'] = $this->elapsedMs($stageStartedAt);
        }

        $stageStartedAt = microtime(true);
        $candidates = $this->metadata->apply($candidates, $filters);
        $metrics['after_metadata_filter'] = count($candidates);
        $timings['metadata_filter'] = $this->elapsedMs($stageStartedAt);

        $stageStartedAt = microtime(true);
        $graphCandidates = $this->graph->expand($candidates, $retrievalProfile);
        $metrics['graph_results'] = count($graphCandidates);
        $timings['graph_expansion'] = $this->elapsedMs($stageStartedAt);

        $stageStartedAt = microtime(true);
        $fused = $this->fusion->fuse([...$candidates, ...$graphCandidates], $retrievalProfile);
        $metrics['fused_results'] = count($fused);
        $timings['fusion'] = $this->elapsedMs($stageStartedAt);

        $stageStartedAt = microtime(true);
        $reranked = $this->reranker->rerank($fused, $analyzed, $retrievalProfile);
        $timings['reranking'] = $this->elapsedMs($stageStartedAt);

        $stageStartedAt = microtime(true);
        $context = $this->context->build($reranked, $retrievalProfile);
        $metrics['context_documents'] = count($context);
        $metrics['expansion_terms'] = count($expansion->terms);
        $metrics['expansion_references'] = count($expansion->references);
        $metrics['profile'] = $retrievalProfile->identifier;
        $timings['context_builder'] = $this->elapsedMs($stageStartedAt);
        $timings['total'] = $this->elapsedMs($startedAt);

        return new RetrievalResult(
            query: $analyzed,
            expansion: $expansion,
            profile: $retrievalProfile,
            context: $context,
            diagnostics: new RetrievalDiagnostics($timings, $metrics),
        );
    }

    /**
     * @param  list<RankedKnowledgeDocumentData>  $results
     * @return list<RetrievalCandidate>
     */
    private function candidates(array $results, string $stage, string $explanation): array
    {
        return array_map(
            static fn (RankedKnowledgeDocumentData $result): RetrievalCandidate => new RetrievalCandidate(
                document: $result->document,
                score: $result->score,
                scoreBreakdown: [$stage => $result->score],
                stages: [$stage],
                explanations: [$explanation],
            ),
            $results,
        );
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
