<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ScriptureRoutingExperimentService
{
    private const Sprint28VectorBaselineK5 = [
        'hit_rate' => 0.5,
        'precision' => 0.167,
        'recall' => 0.25,
        'mrr' => 0.5,
        'ndcg' => 0.436,
        'source_coverage' => 0.75,
    ];

    private const Sprint32CombinedK5 = [
        'hit_rate' => 0.469,
        'precision' => 0.096,
        'recall' => 0.444,
        'mrr' => 0.369,
        'ndcg' => 0.377,
        'source_coverage' => 0.932,
    ];

    public function __construct(
        private Sprint30RetrievalDataset $dataset,
        private ScriptureRoutingSearchService $search,
        private DeterministicScriptureQueryRouter $router,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $mode = 'all', ?int $limit = null, ?string $category = null, ?string $questionId = null): array
    {
        $modes = $mode === 'all' ? $this->search->modes() : [$mode];
        $questions = $this->questions($limit, $category, $questionId);
        $startedAt = microtime(true);
        $results = [];

        foreach ($modes as $modeName) {
            try {
                $results[$modeName] = $this->evaluateMode($modeName, $questions);
            } catch (Throwable $exception) {
                $results[$modeName] = [
                    'status' => 'blocked',
                    'blocking_reason' => $exception->getMessage(),
                ];
            }
        }

        return [
            'decision' => $this->decision($results),
            'experiment' => $this->metadata($startedAt),
            'dataset' => [
                'defined_questions' => count($this->dataset->questions()),
                'evaluated_questions' => count($questions),
                'filters' => [
                    'limit' => $limit,
                    'category' => $category,
                    'question_id' => $questionId,
                ],
            ],
            'classification' => $this->classificationReport($questions),
            'modes' => $results,
            'john_1_1' => $this->johnDiagnostic($modes),
            'false_positives' => $this->falsePositiveDiagnostics(),
            'legacy_source_resolution' => $this->legacySourceResolution(),
            'production_state' => $this->productionState(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    private function evaluateMode(string $mode, array $questions): array
    {
        $rowsByK = [];
        $metrics = [];

        foreach ([5, 10] as $topK) {
            $startedAt = microtime(true);
            $rows = [];

            foreach ($questions as $question) {
                $result = $this->search->search((string) $question['question'], $mode, $topK);
                $rows[] = $this->scoreQuestion($question, $result);
            }

            $rowsByK[$topK] = $rows;
            $metrics['k'.$topK] = $this->summarize($rows, $startedAt);
        }

        return $metrics + [
            'per_route' => $this->summarizeByRoute($rowsByK[5] ?? []),
            'source_distribution' => $this->sourceDistribution($rowsByK[5] ?? []),
            'false_positive_examples' => $this->falsePositiveExamples($rowsByK[5] ?? []),
            'question_samples' => array_slice($rowsByK[5] ?? [], 0, 5),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function scoreQuestion(array $question, ScriptureRoutingResult $result): array
    {
        $ranked = $result->toArray()['results'];
        $references = array_values(array_map(static fn (array $row): string => (string) $row['reference'], $ranked));
        $expected = array_values(array_map('strval', $question['expected_references'] ?? []));
        $relevant = array_values(array_unique(array_intersect($references, $expected)));
        $rank = $this->firstRelevantRank($references, $expected);
        $expectedSourceTypes = array_values(array_map('strval', $question['expected_source_types'] ?? []));
        $retrievedSourceTypes = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['source_type'], $ranked)));
        $foundSourceTypes = array_values(array_intersect($expectedSourceTypes, $retrievedSourceTypes));
        $top = $ranked[0] ?? null;

        return [
            'question_id' => $question['id'] ?? null,
            'category' => $question['category'],
            'question' => $question['question'],
            'route' => $result->classification->route,
            'detected_references' => $result->classification->references,
            'hit' => $relevant !== [],
            'precision' => $ranked === [] ? 0.0 : count($relevant) / count($ranked),
            'recall' => $expected === [] ? 0.0 : count($relevant) / count($expected),
            'reciprocal_rank' => $rank === null ? 0.0 : 1 / $rank,
            'ndcg' => $this->ndcg($references, $expected),
            'source_coverage' => $expectedSourceTypes === [] ? 1.0 : count($foundSourceTypes) / count($expectedSourceTypes),
            'failed' => $relevant === [],
            'top_reference' => $top['reference'] ?? null,
            'top_source_type' => $top['source_type'] ?? null,
            'top_source_name' => $top['source_name'] ?? null,
            'top_origin' => $top['retrieval_origin'] ?? null,
            'top_results' => $ranked,
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
     * @param  list<array<string, mixed>>  $questions
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
     * @param  list<string>  $modes
     * @return array<string, mixed>
     */
    private function johnDiagnostic(array $modes): array
    {
        $diagnostics = [];

        foreach ((array) config('retrieval_sprint33.john_1_1_diagnostic_queries', []) as $query) {
            $query = (string) $query;
            $diagnostics[$query] = [];

            foreach ($modes as $mode) {
                $result = $this->search->search($query, $mode, 10);
                $ranked = $result->toArray()['results'];
                $rank = $this->rankOf($ranked, 'John 1:1');

                $diagnostics[$query][$mode] = [
                    'detected_route' => $result->classification->route,
                    'detected_references' => $result->classification->references,
                    'john_1_1_rank' => $rank,
                    'exact_returned' => $rank !== null,
                    'exact_ranked_first' => $rank === 1,
                    'source_name' => $rank === null ? null : ($ranked[$rank - 1]['source_name'] ?? null),
                    'top_10' => $ranked,
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function falsePositiveDiagnostics(): array
    {
        $diagnostics = [];
        $falsePositiveCount = 0;

        foreach ((array) config('retrieval_sprint33.false_positive_queries', []) as $query) {
            $query = (string) $query;
            $classification = $this->router->classify($query);
            $isFalsePositive = $classification->references !== [];

            if ($isFalsePositive) {
                $falsePositiveCount++;
            }

            $diagnostics[] = [
                'query' => $query,
                'route' => $classification->route,
                'detected_references' => $classification->references,
                'false_positive' => $isFalsePositive,
            ];
        }

        return [
            'total' => count($diagnostics),
            'false_positive_count' => $falsePositiveCount,
            'items' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legacySourceResolution(): array
    {
        $canonical = $this->search->search('John 1:1', 'exact_reference_route', 1)->toArray()['results'][0] ?? null;
        $legacy = $this->search->search('John 1:1', 'exact_reference_route', 1, 'Bible')->toArray()['results'][0] ?? null;

        return [
            'default_source_name' => $canonical['source_name'] ?? null,
            'legacy_source_name' => $legacy['source_name'] ?? null,
            'default_reference' => $canonical['reference'] ?? null,
            'legacy_reference' => $legacy['reference'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function sourceDistribution(array $rows): array
    {
        $counts = [];
        $total = 0;

        foreach ($rows as $row) {
            foreach ((array) $row['top_results'] as $result) {
                $key = ($result['source_type'] ?? 'unknown').'|'.($result['source_name'] ?? 'unknown');
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $total++;
            }
        }

        arsort($counts);

        return [
            'counts' => $counts,
            'total_results' => $total,
            'source_concentration' => $total === 0 || $counts === [] ? 0.0 : round(max($counts) / $total, 6),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function falsePositiveExamples(array $rows): array
    {
        return array_slice(array_values(array_map(
            static fn (array $row): array => [
                'question_id' => $row['question_id'],
                'question' => $row['question'],
                'route' => $row['route'],
                'top_reference' => $row['top_reference'],
                'top_source_type' => $row['top_source_type'],
                'top_source_name' => $row['top_source_name'],
                'top_origin' => $row['top_origin'],
            ],
            array_filter($rows, static fn (array $row): bool => (bool) $row['failed']),
        )), 0, 10);
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
            $questions = array_values(array_filter(
                $questions,
                static fn (array $question): bool => $question['category'] === $category,
            ));
        }

        if ($questionId !== null) {
            $normalized = str_starts_with($questionId, 'q') ? $questionId : sprintf('q%03d', (int) $questionId);
            $questions = array_values(array_filter(
                $questions,
                static fn (array $question): bool => $question['id'] === $normalized,
            ));
        }

        return $limit === null ? $questions : array_slice($questions, 0, max(1, $limit));
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
     * @param  list<array<string, mixed>>  $results
     */
    private function rankOf(array $results, string $reference): ?int
    {
        foreach ($results as $index => $result) {
            if (($result['reference'] ?? null) === $reference) {
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
     * @param  array<string, mixed>  $results
     */
    private function decision(array $results): string
    {
        $hybridRouter = $results['hybrid_router']['k5'] ?? null;

        if (! is_array($hybridRouter)) {
            return 'INCONCLUSIVE';
        }

        if ((float) $hybridRouter['hit_rate'] >= self::Sprint28VectorBaselineK5['hit_rate']
            && (float) $hybridRouter['mrr'] >= self::Sprint28VectorBaselineK5['mrr']) {
            return 'PASS';
        }

        if ((float) $hybridRouter['hit_rate'] > self::Sprint32CombinedK5['hit_rate']) {
            return 'INCONCLUSIVE';
        }

        return 'INCONCLUSIVE';
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(float $startedAt): array
    {
        return [
            'version' => (string) config('retrieval_sprint33.experiment_version', 'retrieval-sprint-33-v1'),
            'config_fingerprint' => $this->fingerprint(config('retrieval_sprint33')),
            'dataset_version' => $this->dataset->version(),
            'dataset_fingerprint' => $this->fingerprint($this->dataset->questions()),
            'corpus_fingerprint' => $this->fingerprint($this->productionState()),
            'sprint28_vector_k5' => self::Sprint28VectorBaselineK5,
            'sprint32_combined_k5' => self::Sprint32CombinedK5,
            'timestamp' => now()->toIso8601String(),
            'runtime_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
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
            'source_types' => KnowledgeDocumentRecord::query()
                ->select('source_type', DB::raw('count(*) as aggregate'))
                ->groupBy('source_type')
                ->pluck('aggregate', 'source_type')
                ->all(),
            'source_names' => KnowledgeDocumentRecord::query()
                ->select('source_name', DB::raw('count(*) as aggregate'))
                ->groupBy('source_name')
                ->pluck('aggregate', 'source_name')
                ->all(),
        ];
    }

    private function fingerprint(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
