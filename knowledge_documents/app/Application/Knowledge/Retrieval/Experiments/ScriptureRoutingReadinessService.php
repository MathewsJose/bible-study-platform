<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Application\Knowledge\Retrieval\DTOs\RetrievalContextDocument;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Facades\DB;

final readonly class ScriptureRoutingReadinessService
{
    private const Sprint33HybridRouterK5 = [
        'hit_rate' => 0.654,
        'mrr' => 0.561,
        'ndcg' => 0.575,
        'hit_at_10' => 0.679,
    ];

    public function __construct(
        private Sprint30RetrievalDataset $dataset,
        private DeterministicScriptureQueryRouter $router,
        private RetrievalEngine $retrieval,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(?int $limit = null, ?string $category = null, ?string $questionId = null): array
    {
        $startedAt = microtime(true);
        $before = $this->productionState();
        $questions = $this->questions($limit, $category, $questionId);

        $production = $this->evaluate($questions, false);
        $integrated = $this->evaluate($questions, true);
        $integratedCitationRows = $integrated['k10']['rows'] ?? [];
        $after = $this->productionState();

        return [
            'decision' => $this->decision($production, $integrated, $before, $after),
            'experiment' => [
                'version' => 'retrieval-sprint-34-v1',
                'feature_flag' => 'retrieval.scripture_router.enabled',
                'feature_flag_default' => (bool) config('retrieval.scripture_router.enabled', false),
                'sprint33_hybrid_router_k5' => self::Sprint33HybridRouterK5,
                'timestamp' => now()->toIso8601String(),
                'runtime_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ],
            'dataset' => [
                'version' => $this->dataset->version(),
                'defined_questions' => count($this->dataset->questions()),
                'evaluated_questions' => count($questions),
            ],
            'classification' => $this->classificationReport($questions),
            'production' => $this->withoutRows($production),
            'integrated_router' => $this->withoutRows($integrated),
            'comparison' => $this->comparison($production, $integrated),
            'false_positives' => $this->falsePositiveDiagnostics(),
            'citation_integrity' => $this->citationIntegrity($integratedCitationRows),
            'production_state_before' => $before,
            'production_state_after' => $after,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    private function evaluate(array $questions, bool $enabled): array
    {
        $original = (bool) config('retrieval.scripture_router.enabled', false);
        config()->set('retrieval.scripture_router.enabled', $enabled);

        try {
            $byK = [];

            foreach ([5, 10] as $topK) {
                $startedAt = microtime(true);
                $rows = [];

                foreach ($questions as $question) {
                    $result = $this->retrieval->retrieve(
                        query: (string) $question['question'],
                        profile: 'search',
                        topK: $topK,
                        contextLimit: $topK,
                        includeExplanations: true,
                    );
                    $rows[] = $this->scoreQuestion($question, $result->context, $result->diagnostics->metrics);
                }

                $byK['k'.$topK] = $this->summarize($rows, $startedAt) + ['rows' => $rows];
            }

            $byK['per_route'] = $this->summarizeByRoute($byK['k5']['rows']);
            $byK['fallback'] = $this->fallbackSummary($byK['k5']['rows']);

            return $byK;
        } finally {
            config()->set('retrieval.scripture_router.enabled', $original);
        }
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  list<RetrievalContextDocument>  $context
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    private function scoreQuestion(array $question, array $context, array $diagnostics): array
    {
        $references = array_map(static fn (RetrievalContextDocument $document): string => $document->candidate->document->reference, $context);
        $expected = array_values(array_map('strval', $question['expected_references'] ?? []));
        $relevant = array_values(array_unique(array_intersect($references, $expected)));
        $rank = $this->firstRelevantRank($references, $expected);
        $classification = $this->router->classify((string) $question['question']);
        $top = $context[0] ?? null;

        return [
            'question_id' => $question['id'] ?? null,
            'category' => $question['category'],
            'question' => $question['question'],
            'route' => $classification->route,
            'detected_references' => $classification->references,
            'hit' => $relevant !== [],
            'precision' => $context === [] ? 0.0 : count($relevant) / count($context),
            'recall' => $expected === [] ? 0.0 : count($relevant) / count($expected),
            'reciprocal_rank' => $rank === null ? 0.0 : 1 / $rank,
            'ndcg' => $this->ndcg($references, $expected),
            'source_coverage' => $this->sourceCoverage($question, $context),
            'failed' => $relevant === [],
            'fallback' => ! isset($diagnostics['scripture_router_enabled']),
            'top_reference' => $top?->candidate->document->reference,
            'top_source_name' => $top?->candidate->document->sourceName,
            'top_source_type' => $top?->candidate->document->sourceType,
            'citations' => array_map(static fn (RetrievalContextDocument $document): array => [
                'id' => $document->candidate->document->id,
                'reference' => $document->candidate->document->reference,
                'source_name' => $document->candidate->document->sourceName,
                'source_type' => $document->candidate->document->sourceType,
            ], $context),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarize(array $rows, float $startedAt): array
    {
        $total = max(1, count($rows));

        return [
            'questions' => count($rows),
            'hit_rate' => round(count(array_filter($rows, static fn (array $row): bool => (bool) $row['hit'])) / $total, 6),
            'precision' => round(array_sum(array_column($rows, 'precision')) / $total, 6),
            'recall' => round(array_sum(array_column($rows, 'recall')) / $total, 6),
            'mrr' => round(array_sum(array_column($rows, 'reciprocal_rank')) / $total, 6),
            'ndcg' => round(array_sum(array_column($rows, 'ndcg')) / $total, 6),
            'source_coverage' => round(array_sum(array_column($rows, 'source_coverage')) / $total, 6),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'failed_questions' => array_values(array_map(
                static fn (array $row): string => (string) $row['question'],
                array_filter($rows, static fn (array $row): bool => (bool) $row['failed']),
            )),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarizeByRoute(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) $row['route']][] = $row;
        }

        $summary = [];
        foreach ($groups as $route => $routeRows) {
            $summary[$route] = $this->summarize($routeRows, microtime(true));
        }

        ksort($summary);

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function fallbackSummary(array $rows): array
    {
        $fallbacks = count(array_filter($rows, static fn (array $row): bool => (bool) $row['fallback']));

        return [
            'fallback_count' => $fallbacks,
            'fallback_success_rate' => $fallbacks === 0 ? 1.0 : round(count(array_filter($rows, static fn (array $row): bool => (bool) $row['fallback'] && (bool) $row['hit'])) / $fallbacks, 6),
        ];
    }

    /**
     * @param  array<string, mixed>  $production
     * @param  array<string, mixed>  $integrated
     * @return array<string, mixed>
     */
    private function comparison(array $production, array $integrated): array
    {
        return [
            'hit_at_5' => ['production' => $production['k5']['hit_rate'], 'sprint33' => self::Sprint33HybridRouterK5['hit_rate'], 'integrated' => $integrated['k5']['hit_rate']],
            'mrr_at_5' => ['production' => $production['k5']['mrr'], 'sprint33' => self::Sprint33HybridRouterK5['mrr'], 'integrated' => $integrated['k5']['mrr']],
            'ndcg_at_5' => ['production' => $production['k5']['ndcg'], 'sprint33' => self::Sprint33HybridRouterK5['ndcg'], 'integrated' => $integrated['k5']['ndcg']],
            'hit_at_10' => ['production' => $production['k10']['hit_rate'], 'sprint33' => self::Sprint33HybridRouterK5['hit_at_10'], 'integrated' => $integrated['k10']['hit_rate']],
            'latency_k5_ms' => ['production' => $production['k5']['latency_ms'], 'integrated' => $integrated['k5']['latency_ms']],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function citationIntegrity(array $rows): array
    {
        $invalid = [];

        foreach ($rows as $row) {
            foreach ((array) $row['citations'] as $citation) {
                $exists = KnowledgeDocumentRecord::query()
                    ->whereKey((string) $citation['id'])
                    ->where('reference', (string) $citation['reference'])
                    ->where('source_name', (string) $citation['source_name'])
                    ->exists();

                if (! $exists) {
                    $invalid[] = $citation;
                }
            }
        }

        return [
            'invalid_references' => count($invalid),
            'citation_mismatches' => $invalid,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function classificationReport(array $questions): array
    {
        $counts = [
            'exact_reference' => 0,
            'reference_contextual' => 0,
            'doctrinal_semantic' => 0,
            'general_semantic' => 0,
            'unclassified' => 0,
        ];

        foreach ($questions as $question) {
            $classification = $this->router->classify((string) $question['question']);
            $counts[$classification->route] = ($counts[$classification->route] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function falsePositiveDiagnostics(): array
    {
        $items = [];
        $count = 0;

        foreach ((array) config('retrieval_sprint33.false_positive_queries', []) as $query) {
            $classification = $this->router->classify((string) $query);
            $falsePositive = $classification->references !== [];
            $count += $falsePositive ? 1 : 0;
            $items[] = [
                'query' => $query,
                'route' => $classification->route,
                'detected_references' => $classification->references,
                'false_positive' => $falsePositive,
            ];
        }

        return [
            'total' => count($items),
            'false_positive_count' => $count,
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $production
     * @param  array<string, mixed>  $integrated
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function decision(array $production, array $integrated, array $before, array $after): string
    {
        if ($before !== $after) {
            return 'BLOCKED';
        }

        if ((float) $integrated['k5']['hit_rate'] >= self::Sprint33HybridRouterK5['hit_rate']
            && (float) $integrated['k5']['mrr'] >= self::Sprint33HybridRouterK5['mrr']
            && (float) $integrated['k5']['hit_rate'] > (float) $production['k5']['hit_rate']) {
            return 'PASS';
        }

        return 'INCONCLUSIVE';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function questions(?int $limit, ?string $category, ?string $questionId): array
    {
        $questions = [];

        foreach ($this->dataset->questions() as $index => $question) {
            $question['id'] = sprintf('q%03d', $index + 1);
            $questions[] = $question;
        }

        if ($category !== null) {
            $questions = array_values(array_filter($questions, static fn (array $question): bool => $question['category'] === $category));
        }

        if ($questionId !== null) {
            $normalized = str_starts_with($questionId, 'q') ? $questionId : sprintf('q%03d', (int) $questionId);
            $questions = array_values(array_filter($questions, static fn (array $question): bool => $question['id'] === $normalized));
        }

        return $limit === null ? $questions : array_slice($questions, 0, max(1, $limit));
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  list<RetrievalContextDocument>  $context
     */
    private function sourceCoverage(array $question, array $context): float
    {
        $expected = array_values(array_map('strval', $question['expected_source_types'] ?? []));

        if ($expected === []) {
            return 1.0;
        }

        $actual = array_values(array_unique(array_map(static fn (RetrievalContextDocument $document): string => $document->candidate->document->sourceType, $context)));

        return count(array_intersect($expected, $actual)) / count($expected);
    }

    /**
     * @param  list<string>  $references
     * @param  list<string>  $expected
     */
    private function firstRelevantRank(array $references, array $expected): ?int
    {
        foreach ($references as $index => $reference) {
            if (in_array($reference, $expected, true)) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $references
     * @param  list<string>  $expected
     */
    private function ndcg(array $references, array $expected): float
    {
        if ($references === [] || $expected === []) {
            return 0.0;
        }

        $dcg = 0.0;
        $seen = [];

        foreach ($references as $index => $reference) {
            if (in_array($reference, $expected, true) && ! isset($seen[$reference])) {
                $dcg += 1 / log($index + 2, 2);
                $seen[$reference] = true;
            }
        }

        $idcg = 0.0;
        for ($index = 0; $index < min(count($references), count($expected)); $index++) {
            $idcg += 1 / log($index + 2, 2);
        }

        return $idcg <= 0.0 ? 0.0 : round($dcg / $idcg, 6);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function withoutRows(array $metrics): array
    {
        foreach (['k5', 'k10'] as $key) {
            unset($metrics[$key]['rows']);
        }

        return $metrics;
    }

    /**
     * @return array<string, mixed>
     */
    private function productionState(): array
    {
        return [
            'documents' => KnowledgeDocumentRecord::query()->count(),
            'embedded_documents' => KnowledgeDocumentRecord::query()->whereNotNull('embedding')->count(),
            'embedding_dimensions' => KnowledgeDocumentRecord::query()
                ->whereNotNull('embedding_dimensions')
                ->select('embedding_dimensions', DB::raw('count(*) as aggregate'))
                ->groupBy('embedding_dimensions')
                ->pluck('aggregate', 'embedding_dimensions')
                ->all(),
            'graph_edges' => DB::table('knowledge_document_relationships')->count(),
        ];
    }
}
