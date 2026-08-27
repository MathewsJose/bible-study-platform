<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Services\HybridSearchService;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DoctrinalBridgeExperimentService
{
    private const Sprint28VectorBaselineK5 = [
        'hit_rate' => 0.5,
        'precision' => 0.167,
        'recall' => 0.25,
        'mrr' => 0.5,
        'ndcg' => 0.436,
        'source_coverage' => 0.75,
    ];

    public function __construct(
        private Sprint30RetrievalDataset $dataset,
        private DoctrinalQueryExpansionService $expansion,
        private HybridSearchService $hybrid,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $mode = 'all', ?int $limit = null, ?string $category = null, ?string $questionId = null): array
    {
        $modes = $mode === 'all' ? $this->expansion->modes() : [$mode];
        $questions = $this->questions($limit, $category, $questionId);
        $startedAt = microtime(true);
        $results = [];

        $blockingReason = $this->preflight($questions);
        if ($blockingReason !== null) {
            return [
                'decision' => 'BLOCKED',
                'blocking_reason' => $blockingReason,
                'experiment' => $this->experimentMetadata($startedAt),
                'dataset' => [
                    'defined_questions' => count($this->dataset->questions()),
                    'evaluated_questions' => count($questions),
                    'filters' => [
                        'limit' => $limit,
                        'category' => $category,
                        'question_id' => $questionId,
                    ],
                ],
                'modes' => [],
                'john_1_1' => [],
                'production_state' => $this->productionState(),
            ];
        }

        $baselineTopReferences = null;
        foreach ($modes as $modeName) {
            try {
                $results[$modeName] = $this->evaluateMode($modeName, $questions, $baselineTopReferences);

                if ($modeName === 'baseline' && isset($results[$modeName]['question_rows'])) {
                    $baselineTopReferences = $this->topReferenceMap((array) $results[$modeName]['question_rows']);
                }

                unset($results[$modeName]['question_rows']);
            } catch (Throwable $exception) {
                $results[$modeName] = [
                    'status' => 'blocked',
                    'blocking_reason' => $exception->getMessage(),
                ];
            }
        }

        return [
            'decision' => $this->decision($results),
            'experiment' => $this->experimentMetadata($startedAt),
            'dataset' => [
                'defined_questions' => count($this->dataset->questions()),
                'evaluated_questions' => count($questions),
                'filters' => [
                    'limit' => $limit,
                    'category' => $category,
                    'question_id' => $questionId,
                ],
            ],
            'modes' => $results,
            'john_1_1' => $this->johnDiagnostic($modes),
            'production_state' => $this->productionState(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    private function preflight(array $questions): ?string
    {
        if ($questions === []) {
            return null;
        }

        try {
            $this->retrieve((string) $questions[0]['question'], 1);
        } catch (Throwable $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * @param  list<string>  $modes
     * @return array<string, mixed>
     */
    private function johnDiagnostic(array $modes): array
    {
        $diagnostics = [];

        foreach ((array) config('retrieval_sprint32.john_1_1_diagnostic_queries', []) as $query) {
            $query = (string) $query;
            $diagnostics[$query] = [];

            foreach ($modes as $mode) {
                $expansion = $this->expansion->expand($query, $mode);
                try {
                    $results = $this->retrieve($expansion->expandedQuery, 10);
                } catch (Throwable $exception) {
                    $diagnostics[$query][$mode] = [
                        'status' => 'blocked',
                        'blocking_reason' => $exception->getMessage(),
                        'expanded_query' => $expansion->expandedQuery,
                        'terms' => $expansion->terms,
                        'query_drift_score' => $expansion->queryDriftScore,
                    ];

                    continue;
                }
                $rank = $this->rankOf($results, 'John 1:1');
                $top = $results[0] ?? null;

                $diagnostics[$query][$mode] = [
                    'john_1_1_rank' => $rank,
                    'appears_top_5' => $rank !== null && $rank <= 5,
                    'appears_top_10' => $rank !== null && $rank <= 10,
                    'top_reference' => $top['reference'] ?? null,
                    'top_source_type' => $top['source_type'] ?? null,
                    'top_source_name' => $top['source_name'] ?? null,
                    'expanded_query' => $expansion->expandedQuery,
                    'terms' => $expansion->terms,
                    'query_drift_score' => $expansion->queryDriftScore,
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @param  array<string, string|null>|null  $baselineTopReferences
     * @return array<string, mixed>
     */
    private function evaluateMode(string $mode, array $questions, ?array $baselineTopReferences): array
    {
        $byK = [];
        $questionRowsByK = [];

        foreach ([5, 10] as $topK) {
            $startedAt = microtime(true);
            $rows = [];

            foreach ($questions as $question) {
                $expansion = $this->expansion->expand((string) $question['question'], $mode);
                $results = $this->retrieve($expansion->expandedQuery, $topK);
                $rows[] = $this->scoreQuestion($question, $results, $expansion);
            }

            $questionRowsByK[$topK] = $rows;
            $byK['k'.$topK] = $this->summarize($rows, $startedAt);
        }

        return $byK + [
            'source_distribution' => $this->sourceDistribution($questionRowsByK[5] ?? []),
            'query_drift' => $this->queryDrift($questionRowsByK[5] ?? []),
            'false_positive_examples' => $this->falsePositiveExamples($questionRowsByK[5] ?? []),
            'changed_top_result_examples' => $this->changedTopResultExamples($questionRowsByK[5] ?? [], $mode, $baselineTopReferences),
            'question_samples' => array_slice($questionRowsByK[5] ?? [], 0, 5),
            'question_rows' => $questionRowsByK[5] ?? [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $questions
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
     * @return list<array<string, mixed>>
     */
    private function retrieve(string $query, int $topK): array
    {
        return array_map(
            static fn (RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData $result): array => [
                'id' => $result->document->id,
                'reference' => $result->document->reference,
                'source_name' => $result->document->sourceName,
                'source_type' => $result->document->sourceType,
                'score' => $result->score,
            ],
            $this->hybrid->search($query, $topK, (float) config('retrieval_sprint32.minimum_score', 0.0)),
        );
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function scoreQuestion(array $question, array $results, QueryExpansionResult $expansion): array
    {
        $references = array_map(static fn (array $result): string => (string) $result['reference'], $results);
        $expected = array_values(array_map('strval', $question['expected_references'] ?? []));
        $relevant = array_values(array_unique(array_intersect($references, $expected)));
        $rank = $this->firstRelevantRank($references, $expected);
        $retrievedSourceTypes = array_values(array_unique(array_map(static fn (array $result): string => (string) $result['source_type'], $results)));
        $expectedSourceTypes = array_values(array_map('strval', $question['expected_source_types'] ?? []));
        $foundSourceTypes = array_values(array_intersect($expectedSourceTypes, $retrievedSourceTypes));
        $top = $results[0] ?? null;

        return [
            'question_id' => $question['id'] ?? null,
            'category' => $question['category'],
            'question' => $question['question'],
            'expanded_query' => $expansion->expandedQuery,
            'expansion_terms' => $expansion->terms,
            'expansion_reasons' => $expansion->reasons,
            'query_drift_score' => $expansion->queryDriftScore,
            'hit' => $relevant !== [],
            'precision' => $results === [] ? 0.0 : count($relevant) / count($results),
            'recall' => $expected === [] ? 0.0 : count($relevant) / count($expected),
            'reciprocal_rank' => $rank === null ? 0.0 : 1 / $rank,
            'ndcg' => $this->ndcg($references, $expected),
            'source_coverage' => $expectedSourceTypes === [] ? 1.0 : count($foundSourceTypes) / count($expectedSourceTypes),
            'failed' => $relevant === [],
            'top_reference' => $top['reference'] ?? null,
            'top_source_type' => $top['source_type'] ?? null,
            'top_source_name' => $top['source_name'] ?? null,
            'top_results' => $results,
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
     * @param  array<string, mixed>  $results
     */
    private function decision(array $results): string
    {
        $baseline = $results['baseline']['k5'] ?? null;
        $combined = $results['combined']['k5'] ?? null;

        if (! is_array($baseline) || ! is_array($combined)) {
            return 'INCONCLUSIVE';
        }

        if ((float) $combined['hit_rate'] >= self::Sprint28VectorBaselineK5['hit_rate']
            && (float) $combined['mrr'] >= self::Sprint28VectorBaselineK5['mrr']) {
            return 'PASS';
        }

        if ((float) $combined['hit_rate'] < (float) $baseline['hit_rate']
            || (float) $combined['mrr'] < (float) $baseline['mrr']) {
            return 'REGRESSION';
        }

        return 'INCONCLUSIVE';
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
        $topCount = $counts === [] ? 0 : max($counts);

        return [
            'counts' => $counts,
            'total_results' => $total,
            'source_concentration' => $total === 0 ? 0.0 : round($topCount / $total, 6),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function queryDrift(array $rows): array
    {
        $scores = array_map(static fn (array $row): float => (float) $row['query_drift_score'], $rows);
        $threshold = (float) config('retrieval_sprint32.query_drift_warning_threshold', 2.5);

        return [
            'average' => $scores === [] ? 0.0 : round(array_sum($scores) / count($scores), 6),
            'maximum' => $scores === [] ? 0.0 : max($scores),
            'warning_threshold' => $threshold,
            'warnings' => array_values(array_map(
                static fn (array $row): array => [
                    'question_id' => $row['question_id'],
                    'question' => $row['question'],
                    'score' => $row['query_drift_score'],
                ],
                array_filter($rows, static fn (array $row): bool => (float) $row['query_drift_score'] >= $threshold),
            )),
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
                'expected_references_missing' => true,
                'top_reference' => $row['top_reference'],
                'top_source_type' => $row['top_source_type'],
                'top_source_name' => $row['top_source_name'],
            ],
            array_filter($rows, static fn (array $row): bool => (bool) $row['failed']),
        )), 0, 10);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string|null>|null  $baselineTopReferences
     * @return list<array<string, mixed>>
     */
    private function changedTopResultExamples(array $rows, string $mode, ?array $baselineTopReferences): array
    {
        if ($mode === 'baseline' || $baselineTopReferences === null) {
            return [];
        }

        $examples = [];

        foreach ($rows as $row) {
            $questionId = (string) ($row['question_id'] ?? '');
            $baselineReference = $baselineTopReferences[$questionId] ?? null;
            $expandedReference = is_string($row['top_reference'] ?? null) ? (string) $row['top_reference'] : null;

            if ($baselineReference !== $expandedReference) {
                $examples[] = [
                    'question_id' => $questionId,
                    'question' => $row['question'],
                    'baseline_top_reference' => $baselineReference,
                    'expanded_top_reference' => $expandedReference,
                ];
            }
        }

        return array_slice($examples, 0, 10);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string|null>
     */
    private function topReferenceMap(array $rows): array
    {
        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row['question_id']] = is_string($row['top_reference'] ?? null) ? (string) $row['top_reference'] : null;
        }

        return $map;
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
     * @param  mixed  $value
     */
    private function fingerprint(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function corpusFingerprint(): string
    {
        return $this->fingerprint($this->productionState());
    }

    /**
     * @return array<string, mixed>
     */
    private function experimentMetadata(float $startedAt): array
    {
        return [
            'version' => $this->expansion->version(),
            'config_fingerprint' => $this->expansion->fingerprint(),
            'dataset_version' => $this->dataset->version(),
            'dataset_fingerprint' => $this->fingerprint($this->dataset->questions()),
            'corpus_fingerprint' => $this->corpusFingerprint(),
            'retriever' => (string) config('retrieval_sprint32.retriever', 'hybrid'),
            'minimum_score' => (float) config('retrieval_sprint32.minimum_score', 0.0),
            'timestamp' => now()->toIso8601String(),
            'runtime_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productionState(): array
    {
        $dimensions = KnowledgeDocumentRecord::query()
            ->whereNotNull('embedding_dimensions')
            ->select('embedding_dimensions', DB::raw('count(*) as aggregate'))
            ->groupBy('embedding_dimensions')
            ->pluck('aggregate', 'embedding_dimensions')
            ->all();

        return [
            'documents' => KnowledgeDocumentRecord::query()->count(),
            'embedded_documents' => KnowledgeDocumentRecord::query()->whereNotNull('embedding')->count(),
            'embedding_dimensions' => $dimensions,
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
}
