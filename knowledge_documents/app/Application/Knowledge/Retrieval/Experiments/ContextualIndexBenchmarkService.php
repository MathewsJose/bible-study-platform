<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Infrastructure\Knowledge\Persistence\RetrievalContextualDocumentRecord;

final readonly class ContextualIndexBenchmarkService
{
    /** @var array<string, array<string, float|int>> */
    private const Sprint28K5Baseline = [
        'vector' => [
            'hit_rate' => 0.5,
            'precision' => 0.167,
            'recall' => 0.25,
            'mrr' => 0.5,
            'ndcg' => 0.436,
            'source_coverage' => 0.75,
            'latency_ms' => 107,
        ],
        'lexical' => [
            'hit_rate' => 0.333,
            'precision' => 0.1,
            'recall' => 0.25,
            'mrr' => 0.208,
            'ndcg' => 0.186,
            'source_coverage' => 0.75,
            'latency_ms' => 106,
        ],
        'hybrid' => [
            'hit_rate' => 0.5,
            'precision' => 0.167,
            'recall' => 0.25,
            'mrr' => 0.417,
            'ndcg' => 0.384,
            'source_coverage' => 0.75,
            'latency_ms' => 251,
        ],
    ];

    public function __construct(
        private Sprint30RetrievalDataset $dataset,
        private ContextualIndexSearchService $search,
        private ContextualRetrievalIndexService $index,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $window = 'plus_minus_1', ?int $limit = null): array
    {
        $allQuestions = $this->dataset->questions();
        $questions = $limit === null ? $allQuestions : array_slice($allQuestions, 0, max(1, $limit));
        $window = $this->index->window($window);
        $indexed = RetrievalContextualDocumentRecord::query()->where('context_window', $window)->count();
        $embedded = RetrievalContextualDocumentRecord::query()->where('context_window', $window)->whereNotNull('embedding')->count();

        $result = [
            'decision' => 'BLOCKED',
            'sprint28_baseline' => self::Sprint28K5Baseline,
            'dataset' => [
                'version' => $this->dataset->version(),
                'defined_questions' => count($allQuestions),
                'evaluated_questions' => count($questions),
                'validation' => $this->dataset->validate(),
            ],
            'index' => [
                'window' => $window,
                'indexed_documents' => $indexed,
                'embedded_documents' => $embedded,
                'fingerprint' => $this->index->fingerprint(),
            ],
            'metrics' => [],
            'by_category' => [],
            'john_1_1' => [],
            'citation_integrity' => [
                'invalid_reference_count' => 0,
                'invalid_references' => [],
            ],
        ];

        if ($embedded === 0) {
            $result['blocking_reason'] = 'Contextual index embeddings are not generated for the selected window.';

            return $result;
        }

        $rowsByK = [];

        foreach ([5, 10] as $topK) {
            $startedAt = microtime(true);
            $rows = [];

            foreach ($questions as $question) {
                $results = $this->search->search($question['question'], $window, $topK);
                $rows[] = $this->scoreQuestion($question, $results);
            }

            $rowsByK[$topK] = $rows;
            $result['metrics']['k'.$topK] = $this->summarize($rows, $startedAt);
        }

        $result['by_category'] = $this->summarizeByCategory($rowsByK[10]);
        $result['john_1_1'] = $this->johnDiagnostic($window);
        $result['citation_integrity'] = $this->citationIntegrity($window);
        $result['decision'] = $this->decision($result['metrics']['k5']);

        return $result;
    }

    /**
     * @param  array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}  $question
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function scoreQuestion(array $question, array $results): array
    {
        $references = array_map(static fn (array $result): string => (string) $result['reference'], $results);
        $expected = $question['expected_references'];
        $relevant = array_values(array_unique(array_intersect($references, $expected)));
        $rank = $this->firstRelevantRank($references, $expected);
        $retrievedSourceTypes = array_values(array_unique(array_map(static fn (array $result): string => (string) $result['source_type'], $results)));
        $foundSourceTypes = array_values(array_intersect($question['expected_source_types'], $retrievedSourceTypes));

        return [
            'category' => $question['category'],
            'question' => $question['question'],
            'hit' => $relevant !== [],
            'precision' => $results === [] ? 0.0 : count($relevant) / count($results),
            'recall' => $expected === [] ? 0.0 : count($relevant) / count($expected),
            'reciprocal_rank' => $rank === null ? 0.0 : 1 / $rank,
            'ndcg' => $this->ndcg($references, $expected),
            'source_coverage' => $question['expected_source_types'] === [] ? 1.0 : count($foundSourceTypes) / count($question['expected_source_types']),
            'failed' => $relevant === [],
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
     * @param  array<string, mixed>  $metrics
     */
    private function decision(array $metrics): string
    {
        return (float) $metrics['hit_rate'] >= self::Sprint28K5Baseline['vector']['hit_rate']
            && (float) $metrics['mrr'] >= self::Sprint28K5Baseline['vector']['mrr']
            ? 'PASS'
            : 'REGRESSION';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarizeByCategory(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $category = (string) $row['category'];
            $groups[$category][] = $row;
        }

        return collect($groups)
            ->map(fn (array $categoryRows): array => $this->summarize($categoryRows, microtime(true)))
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function johnDiagnostic(string $window): array
    {
        $queries = [
            'What does John 1:1 say?',
            'Why does John 1:1 teach that the Word is God?',
            'What does the Bible teach about the divinity of the Word?',
        ];

        $diagnostics = [];

        foreach ($queries as $query) {
            $results = $this->search->search($query, $window, 10);
            $diagnostics[$query] = [
                'john_1_1_rank' => $this->rankOf($results, 'John 1:1'),
                'top_10' => array_map(static fn (array $result): array => [
                    'reference' => $result['reference'],
                    'source_type' => $result['source_type'],
                    'source_name' => $result['source_name'],
                    'score' => $result['score'],
                ], $results),
            ];
        }

        return $diagnostics;
    }

    /**
     * @return array{invalid_reference_count: int, invalid_references: list<string>}
     */
    private function citationIntegrity(string $window): array
    {
        $references = RetrievalContextualDocumentRecord::query()
            ->where('context_window', $window)
            ->pluck('reference')
            ->map(static fn (mixed $reference): string => (string) $reference)
            ->filter(static fn (string $reference): bool => ! preg_match('/^(?:[1-3]\s+)?[A-Za-z]+(?:\s+[A-Za-z]+)*\s+\d+(?::\d+)?$/', $reference))
            ->values()
            ->all();

        return [
            'invalid_reference_count' => count($references),
            'invalid_references' => array_slice($references, 0, 25),
        ];
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
}
