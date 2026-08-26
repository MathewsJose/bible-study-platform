<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Services\HybridSearchService;
use App\Application\Knowledge\Services\LexicalSearchService;
use App\Application\Knowledge\Services\SemanticSearchService;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;

final readonly class ContextualRetrievalExperimentService
{
    public function __construct(
        private Sprint30RetrievalDataset $dataset,
        private SemanticSearchService $semantic,
        private LexicalSearchService $lexical,
        private HybridSearchService $hybrid,
        private EmbeddingProviderInterface $embeddings,
        private ContextualBibleTextBuilder $contextBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  list<string>|null  $contextModes
     * @return array<string, mixed>
     */
    public function run(?int $limit = null, ?array $contextModes = null): array
    {
        $allQuestions = $this->dataset->questions();
        $questions = $limit === null ? $allQuestions : array_slice($allQuestions, 0, max(1, $limit));
        $contextModes ??= ['verse_only'];

        return [
            'dataset' => [
                'version' => $this->dataset->version(),
                'validation' => $this->dataset->validate(),
                'defined_questions' => count($allQuestions),
                'evaluated_questions' => count($questions),
                'categories' => $this->categoryCounts($allQuestions),
                'evaluated_categories' => $this->categoryCounts($questions),
            ],
            'production' => [
                'vector' => $this->evaluateRetriever($questions, 'vector'),
                'lexical' => $this->evaluateRetriever($questions, 'lexical'),
                'hybrid' => $this->evaluateRetriever($questions, 'hybrid'),
            ],
            'contextual' => [
                'experiment_a_verse_only' => $this->contextModeResult($questions, $contextModes, 'verse_only'),
                'experiment_b_adjacent' => $this->contextModeResult($questions, $contextModes, 'adjacent'),
                'experiment_c_window_3' => $this->contextModeResult($questions, $contextModes, 'window_3'),
                'experiment_d_labeled_verse' => $this->contextModeResult($questions, $contextModes, 'labeled_verse'),
                'experiment_e_chapter_context' => $this->contextModeResult($questions, $contextModes, 'chapter_context'),
            ],
            'document_type_weighting' => [
                'verse_only' => $this->evaluateRetriever($questions, 'vector', ['source_type' => 'bible_verse']),
                'chapter_only' => $this->evaluateRetriever($questions, 'vector', ['source_type' => 'bible_chapter']),
                'equal_weight' => $this->evaluateWeightedHybrid($questions, 1.0, 1.0),
                'verse_preferred' => $this->evaluateWeightedHybrid($questions, 1.08, 0.92),
                'chapter_preferred' => $this->evaluateWeightedHybrid($questions, 0.92, 1.08),
            ],
            'exact_reference_boosting' => $this->evaluateExactReferenceBoosting($questions),
            'john_1_1_diagnostic' => $this->johnDiagnostic($contextModes),
        ];
    }

    /**
     * @param  list<array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}>  $questions
     * @param  list<string>  $enabledModes
     * @return array<string, mixed>
     */
    private function contextModeResult(array $questions, array $enabledModes, string $mode): array
    {
        if (! in_array($mode, $enabledModes, true)) {
            return [
                'status' => 'blocked',
                'reason' => 'Not run in this bounded diagnostic pass because contextual embedding generation is expensive. Use --context-modes='.$mode.' to run this mode explicitly.',
            ];
        }

        return $this->evaluateContextual($questions, $mode) + ['status' => 'completed'];
    }

    /**
     * @param  list<array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}>  $questions
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function evaluateRetriever(array $questions, string $strategy, array $filters = []): array
    {
        $byK = [];

        foreach ([5, 10] as $topK) {
            $startedAt = microtime(true);
            $rows = [];

            foreach ($questions as $question) {
                $results = $this->retrieve($question['question'], $topK, $strategy, $filters);
                $rows[] = $this->scoreQuestion($question, $results);
            }

            $byK["k{$topK}"] = $this->summarize($rows, $startedAt);
        }

        return $byK;
    }

    /**
     * @param  list<array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}>  $questions
     * @return array<string, mixed>
     */
    private function evaluateContextual(array $questions, string $mode): array
    {
        $byK = [];

        foreach ([5, 10] as $topK) {
            $startedAt = microtime(true);
            $rows = [];

            foreach ($questions as $question) {
                $results = $this->contextualResults($question, $mode, $topK);
                $rows[] = $this->scoreQuestion($question, $results);
            }

            $byK["k{$topK}"] = $this->summarize($rows, $startedAt);
        }

        return $byK + [
            'scope' => 'candidate-pool reranking only; production embeddings are not replaced',
        ];
    }

    /**
     * @param  array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}  $question
     * @return list<array<string, mixed>>
     */
    private function contextualResults(array $question, string $mode, int $topK): array
    {
        $candidates = $this->candidateDocuments($question);
        $queryEmbedding = array_values($this->embeddings->embed($question['question']));
        $texts = $candidates->map(fn (KnowledgeDocumentRecord $document): string => $this->contextualText($document, $mode))->values()->all();
        $embeddings = [];

        foreach (array_chunk($texts, (int) config('retrieval_sprint30.embedding_batch_size', 64)) as $chunk) {
            $embeddings = [...$embeddings, ...$this->embeddings->embedMany($chunk)];
        }

        $ranked = [];
        foreach ($candidates->values() as $index => $document) {
            $ranked[] = [
                'id' => $document->id,
                'reference' => $document->reference,
                'source_name' => $document->source_name,
                'source_type' => $document->source_type,
                'score' => $this->cosine($queryEmbedding, array_values($embeddings[$index] ?? [])),
                'context_mode' => $mode,
            ];
        }

        usort($ranked, static fn (array $first, array $second): int => $second['score'] <=> $first['score']);

        return array_slice($ranked, 0, $topK);
    }

    private function contextualText(KnowledgeDocumentRecord $document, string $mode): string
    {
        return match ($mode) {
            'verse_only' => $document->content,
            'adjacent' => $this->contextBuilder->build($document, 1),
            'window_3' => $this->contextBuilder->build($document, 3),
            'chapter_context' => $this->chapterContext($document),
            default => $this->contextBuilder->build($document, 0),
        };
    }

    private function chapterContext(KnowledgeDocumentRecord $document): string
    {
        if ($document->source_type !== 'bible_verse') {
            return $document->content;
        }

        $chapterReference = preg_replace('/:\d+$/', '', $document->reference);
        $chapter = KnowledgeDocumentRecord::query()
            ->where('source_type', 'bible_chapter')
            ->where('source_name', $document->source_name)
            ->where('reference', $chapterReference)
            ->first(['reference', 'content']);

        return implode("\n", array_filter([
            'Target reference: '.$document->reference,
            'Target verse: '.$document->content,
            $chapter ? 'Chapter context '.$chapter->reference.': '.$chapter->content : null,
        ]));
    }

    /**
     * @param  array{expected_references: list<string>, question: string}  $question
     * @return Collection<int, KnowledgeDocumentRecord>
     */
    private function candidateDocuments(array $question): Collection
    {
        $ids = [];

        foreach ($this->retrieve($question['question'], (int) config('retrieval_sprint30.candidate_limit', 40), 'vector') as $result) {
            $ids[$result['id']] = true;
        }

        foreach ($this->retrieve($question['question'], (int) config('retrieval_sprint30.candidate_limit', 40), 'hybrid') as $result) {
            $ids[$result['id']] = true;
        }

        $expected = KnowledgeDocumentRecord::query()
            ->whereIn('reference', $question['expected_references'])
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        foreach ($expected as $id) {
            $ids[$id] = true;
        }

        return KnowledgeDocumentRecord::query()
            ->whereIn('id', array_keys($ids))
            ->get();
    }

    /**
     * @param  list<array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}>  $questions
     * @return array<string, mixed>
     */
    private function evaluateWeightedHybrid(array $questions, float $verseWeight, float $chapterWeight): array
    {
        $byK = [];

        foreach ([5, 10] as $topK) {
            $startedAt = microtime(true);
            $rows = [];

            foreach ($questions as $question) {
                $results = array_map(function (array $result) use ($verseWeight, $chapterWeight): array {
                    $multiplier = match ($result['source_type']) {
                        'bible_verse' => $verseWeight,
                        'bible_chapter' => $chapterWeight,
                        default => 1.0,
                    };

                    $result['score'] = round((float) $result['score'] * $multiplier, 6);
                    $result['diagnostic_multiplier'] = $multiplier;

                    return $result;
                }, $this->retrieve($question['question'], $topK * 3, 'hybrid'));

                usort($results, static fn (array $first, array $second): int => $second['score'] <=> $first['score']);
                $rows[] = $this->scoreQuestion($question, array_slice($results, 0, $topK));
            }

            $byK["k{$topK}"] = $this->summarize($rows, $startedAt);
        }

        return $byK;
    }

    /**
     * @param  list<array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}>  $questions
     * @return array<string, mixed>
     */
    private function evaluateExactReferenceBoosting(array $questions): array
    {
        $byK = [];

        foreach ([5, 10] as $topK) {
            $startedAt = microtime(true);
            $rows = [];

            foreach ($questions as $question) {
                $boosted = $this->exactReferenceCandidates($question['question']);
                $seen = array_fill_keys(array_column($boosted, 'id'), true);

                foreach ($this->retrieve($question['question'], $topK, 'hybrid') as $result) {
                    if (! isset($seen[$result['id']])) {
                        $boosted[] = $result;
                    }
                }

                $rows[] = $this->scoreQuestion($question, array_slice($boosted, 0, $topK));
            }

            $byK["k{$topK}"] = $this->summarize($rows, $startedAt);
        }

        return $byK;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exactReferenceCandidates(string $query): array
    {
        preg_match_all('/\b(?:[1-3]\s+)?[A-Z][a-z]+(?:\s+[A-Z][a-z]+)?\s+\d+:\d+\b/', $query, $matches);
        $references = array_values(array_unique($matches[0] ?? []));

        if ($references === []) {
            return [];
        }

        return KnowledgeDocumentRecord::query()
            ->whereIn('reference', $references)
            ->orderByRaw("case when source_name = 'Douay-Rheims Bible' then 0 else 1 end")
            ->get()
            ->map(static fn (KnowledgeDocumentRecord $document): array => [
                'id' => $document->id,
                'reference' => $document->reference,
                'source_name' => $document->source_name,
                'source_type' => $document->source_type,
                'score' => 1.5,
                'boost' => 'exact_reference',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  list<string>  $contextModes
     * @return array<string, mixed>
     */
    private function johnDiagnostic(array $contextModes): array
    {
        $queries = [
            'What does John 1:1 say?',
            'Explain John 1:1.',
            'Why does John 1:1 teach that the Word is God?',
            'Why do Christians believe that the Word is divine?',
            'What does the Bible teach about the divinity of the Word?',
            'How does the Gospel of John present the Word?',
        ];

        $diagnostics = [];

        foreach ($queries as $query) {
            $diagnostics[$query] = [];

            foreach (['vector', 'lexical', 'hybrid'] as $strategy) {
                $results = $this->retrieve($query, 10, $strategy);
                $diagnostics[$query][$strategy] = [
                    'john_1_1_rank' => $this->rankOf($results, 'John 1:1'),
                    'top_10' => array_map(static fn (array $result): array => [
                        'reference' => $result['reference'],
                        'source_type' => $result['source_type'],
                        'source_name' => $result['source_name'],
                        'score' => $result['score'],
                    ], $results),
                ];
            }

            foreach (['verse_only', 'adjacent', 'window_3', 'chapter_context'] as $mode) {
                if (! in_array($mode, $contextModes, true)) {
                    $diagnostics[$query]['contextual_'.$mode] = [
                        'status' => 'blocked',
                        'reason' => 'Not run in this bounded diagnostic pass.',
                    ];

                    continue;
                }

                $results = $this->contextualResults([
                    'category' => 'john_diagnostic',
                    'question' => $query,
                    'expected_references' => ['John 1:1'],
                    'expected_source_types' => ['bible_verse'],
                ], $mode, 10);

                $diagnostics[$query]['contextual_'.$mode] = [
                    'john_1_1_rank' => $this->rankOf($results, 'John 1:1'),
                    'top_10' => array_map(static fn (array $result): array => [
                        'reference' => $result['reference'],
                        'source_type' => $result['source_type'],
                        'source_name' => $result['source_name'],
                        'score' => $result['score'],
                    ], $results),
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function retrieve(string $query, int $topK, string $strategy, array $filters = []): array
    {
        $results = match ($strategy) {
            'lexical' => $this->lexical->search($query, $topK, $filters),
            'hybrid' => $this->hybrid->search($query, $topK, 0.0, $filters),
            default => $this->semantic->search($query, $topK, 0.0, $filters),
        };

        return array_map(static fn (RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData $result): array => [
            'id' => $result->document->id,
            'reference' => $result->document->reference,
            'source_name' => $result->document->sourceName,
            'source_type' => $result->document->sourceType,
            'score' => $result->score,
        ], $results);
    }

    /**
     * @param  array{question: string, expected_references: list<string>, expected_source_types: list<string>}  $question
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
            'question' => $question['question'],
            'hit' => $relevant !== [],
            'precision' => $results === [] ? 0.0 : count($relevant) / count($results),
            'recall' => $expected === [] ? 0.0 : count(array_unique($relevant)) / count($expected),
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
     * @param  list<array{category: string}>  $questions
     * @return array<string, int>
     */
    private function categoryCounts(array $questions): array
    {
        $counts = [];

        foreach ($questions as $question) {
            $counts[$question['category']] = ($counts[$question['category']] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
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
     * @param  list<float>  $first
     * @param  list<float>  $second
     */
    private function cosine(array $first, array $second): float
    {
        $dimensions = min(count($first), count($second));
        if ($dimensions === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $firstMagnitude = 0.0;
        $secondMagnitude = 0.0;

        for ($index = 0; $index < $dimensions; $index++) {
            $left = (float) $first[$index];
            $right = (float) $second[$index];
            $dot += $left * $right;
            $firstMagnitude += $left ** 2;
            $secondMagnitude += $right ** 2;
        }

        if ($firstMagnitude === 0.0 || $secondMagnitude === 0.0) {
            return 0.0;
        }

        return round($dot / (sqrt($firstMagnitude) * sqrt($secondMagnitude)), 6);
    }
}
