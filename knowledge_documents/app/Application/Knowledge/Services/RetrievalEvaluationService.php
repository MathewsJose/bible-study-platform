<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\DTOs\EvaluationDatasetValidationResult;
use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RetrievalEvaluationResult;
use App\Application\Knowledge\DTOs\RetrievalEvaluationSummary;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Infrastructure\Knowledge\Persistence\RetrievalEvaluationRunRecord;
use App\Infrastructure\Knowledge\Persistence\RetrievalEvaluationSummaryRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final readonly class RetrievalEvaluationService
{
    public function __construct(
        private SemanticSearchService $semanticSearch,
        private LexicalSearchService $lexicalSearch,
        private HybridSearchService $hybridSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, EvaluationQuestionRecord>
     */
    public function questions(array $options = []): Collection
    {
        return EvaluationQuestionRecord::query()
            ->when($options['questionId'] ?? null, fn (Builder $query, string $questionId): Builder => $query->whereKey($questionId))
            ->when($options['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
            ->orderBy('category')
            ->orderBy('question')
            ->when($options['limit'] ?? null, fn (Builder $query, int $limit): Builder => $query->limit($limit))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function validateDataset(array $options = []): EvaluationDatasetValidationResult
    {
        $questions = $this->questions($options);
        $missingReferences = [];
        $invalidSourceTypes = [];
        $duplicateExpectedReferences = [];
        $questionsWithoutExpectedReferences = [];
        $invalidQuestionIds = [];
        $validSourceTypes = SourceType::values();

        foreach ($questions as $question) {
            $expectedReferences = $this->uniqueStrings($question->expected_references ?? []);
            $rawExpectedReferences = $this->stringList($question->expected_references ?? []);
            $expectedSourceTypes = $this->stringList($question->expected_source_types ?? []);

            if ($expectedReferences === []) {
                $questionsWithoutExpectedReferences[] = [
                    'question_id' => $question->id,
                    'question' => $question->question,
                ];
                $invalidQuestionIds[$question->id] = true;
            }

            $duplicates = array_values(array_unique(array_diff_assoc($rawExpectedReferences, array_unique($rawExpectedReferences))));
            if ($duplicates !== []) {
                $duplicateExpectedReferences[] = [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'references' => $duplicates,
                ];
                $invalidQuestionIds[$question->id] = true;
            }

            $missing = array_values(array_filter(
                $expectedReferences,
                static fn (string $reference): bool => ! KnowledgeDocumentRecord::query()->where('reference', $reference)->exists(),
            ));

            if ($missing !== []) {
                $missingReferences[] = [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'references' => $missing,
                ];
                $invalidQuestionIds[$question->id] = true;
            }

            $invalidTypes = array_values(array_diff($expectedSourceTypes, $validSourceTypes));
            if ($invalidTypes !== []) {
                $invalidSourceTypes[] = [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'source_types' => $invalidTypes,
                ];
                $invalidQuestionIds[$question->id] = true;
            }
        }

        return new EvaluationDatasetValidationResult(
            totalQuestions: $questions->count(),
            validQuestions: $questions->count() - count($invalidQuestionIds),
            invalidQuestions: count($invalidQuestionIds),
            missingReferences: $missingReferences,
            invalidSourceTypes: $invalidSourceTypes,
            duplicateExpectedReferences: $duplicateExpectedReferences,
            questionsWithoutExpectedReferences: $questionsWithoutExpectedReferences,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function evaluate(array $options = []): RetrievalEvaluationSummary
    {
        $topK = max(1, (int) ($options['topK'] ?? 5));
        $minimumScore = isset($options['minimumScore']) ? (float) $options['minimumScore'] : null;
        $strategy = (string) ($options['strategy'] ?? 'vector');
        $save = (bool) ($options['save'] ?? false);
        $threshold = $minimumScore ?? 0.0;

        $results = $this->questions($options)
            ->filter(fn (EvaluationQuestionRecord $question): bool => $this->uniqueStrings($question->expected_references ?? []) !== [])
            ->map(fn (EvaluationQuestionRecord $question): RetrievalEvaluationResult => $this->evaluateQuestion($question, $topK, $threshold, $strategy))
            ->values()
            ->all();

        $summary = $this->summarize($results, $this->configuration($topK, $minimumScore, $options));

        if ($save) {
            $summary = $this->storeSummary($summary, $results, $topK, $minimumScore, $strategy);
        }

        Log::info('Retrieval evaluation completed.', [
            'total_questions' => $summary->totalQuestions,
            'hit_rate' => $summary->hitRate,
            'mean_precision' => $summary->meanPrecision,
            'mean_recall' => $summary->meanRecall,
            'mrr' => $summary->mrr,
            'average_latency_ms' => $summary->averageLatencyMs,
            'saved' => $save,
        ]);

        return $summary;
    }

    /**
     * @return array<string, RetrievalEvaluationSummary>
     */
    public function compare(array $options = []): array
    {
        $summaries = [];

        foreach (['vector', 'lexical', 'hybrid'] as $strategy) {
            $summaries[$strategy] = $this->evaluate(array_merge($options, [
                'strategy' => $strategy,
                'save' => false,
            ]));
        }

        return $summaries;
    }

    /**
     * @return array<string, RetrievalEvaluationSummary>
     */
    public function weightGrid(array $options = []): array
    {
        $summaries = [];
        $originalVectorWeight = config('retrieval.hybrid.vector_weight');
        $originalLexicalWeight = config('retrieval.hybrid.lexical_weight');

        try {
            foreach ([[0.8, 0.2], [0.7, 0.3], [0.6, 0.4]] as [$vectorWeight, $lexicalWeight]) {
                config()->set('retrieval.hybrid.vector_weight', $vectorWeight);
                config()->set('retrieval.hybrid.lexical_weight', $lexicalWeight);

                $key = "vector {$vectorWeight} / lexical {$lexicalWeight}";
                $summaries[$key] = $this->evaluate(array_merge($options, [
                    'strategy' => 'hybrid',
                    'save' => false,
                ]));
            }
        } finally {
            config()->set('retrieval.hybrid.vector_weight', $originalVectorWeight);
            config()->set('retrieval.hybrid.lexical_weight', $originalLexicalWeight);
        }

        return $summaries;
    }

    public function evaluateQuestion(EvaluationQuestionRecord $question, int $topK, float $minimumScore, string $strategy = 'vector'): RetrievalEvaluationResult
    {
        $startedAt = microtime(true);
        $rankedDocuments = $this->retrieve($question->question, $topK, $minimumScore, $strategy);
        $executionTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        $expectedReferences = $this->uniqueStrings($question->expected_references ?? []);
        $retrievedResults = array_map(
            static fn (RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData $result): array => [
                'id' => $result->document->id,
                'source_type' => $result->document->sourceType,
                'source_name' => $result->document->sourceName,
                'tradition' => $result->document->tradition,
                'reference' => $result->document->reference,
                'title' => $result->document->title,
                'score' => $result->score,
                'vector_score' => $result instanceof HybridRankedKnowledgeDocumentData ? ($result->scoreBreakdown['vector'] ?? 0.0) : null,
                'lexical_score' => $result instanceof HybridRankedKnowledgeDocumentData ? ($result->scoreBreakdown['lexical'] ?? 0.0) : null,
            ],
            $rankedDocuments,
        );

        $retrievedReferences = array_map(
            static fn (array $result): string => (string) $result['reference'],
            $retrievedResults,
        );

        $relevantRetrieved = array_values(array_intersect($retrievedReferences, $expectedReferences));
        $firstRelevantRank = $this->firstRelevantRank($retrievedReferences, $expectedReferences);

        return new RetrievalEvaluationResult(
            question: $question,
            expectedReferences: $expectedReferences,
            retrievedResults: $retrievedResults,
            hit: $relevantRetrieved !== [],
            precision: count($retrievedResults) === 0 ? 0.0 : round(count($relevantRetrieved) / count($retrievedResults), 6),
            recall: $expectedReferences === [] ? 0.0 : round(count(array_unique($relevantRetrieved)) / count($expectedReferences), 6),
            reciprocalRank: $firstRelevantRank === null ? 0.0 : round(1 / $firstRelevantRank, 6),
            executionTimeMs: $executionTimeMs,
        );
    }

    /**
     * @param  list<RetrievalEvaluationResult>  $results
     * @param  array<string, mixed>  $configuration
     */
    private function summarize(array $results, array $configuration): RetrievalEvaluationSummary
    {
        $total = count($results);

        if ($total === 0) {
            return new RetrievalEvaluationSummary(0, 0.0, 0.0, 0.0, 0.0, 0, $configuration, []);
        }

        return new RetrievalEvaluationSummary(
            totalQuestions: $total,
            hitRate: round(count(array_filter($results, static fn (RetrievalEvaluationResult $result): bool => $result->hit)) / $total, 6),
            meanPrecision: $this->mean(array_map(static fn (RetrievalEvaluationResult $result): float => $result->precision, $results)),
            meanRecall: $this->mean(array_map(static fn (RetrievalEvaluationResult $result): float => $result->recall, $results)),
            mrr: $this->mean(array_map(static fn (RetrievalEvaluationResult $result): float => $result->reciprocalRank, $results)),
            averageLatencyMs: (int) round(array_sum(array_map(static fn (RetrievalEvaluationResult $result): int => $result->executionTimeMs, $results)) / $total),
            configuration: $configuration,
            results: $results,
        );
    }

    /**
     * @param  list<RetrievalEvaluationResult>  $results
     */
    private function storeSummary(RetrievalEvaluationSummary $summary, array $results, int $topK, ?float $minimumScore, string $strategy): RetrievalEvaluationSummary
    {
        foreach ($results as $result) {
            RetrievalEvaluationRunRecord::query()->create([
                'evaluation_question_id' => $result->question->id,
                'query' => $result->question->question,
                'top_k' => $topK,
                'minimum_score' => $minimumScore,
                'retrieval_strategy' => $strategy,
                'retrieved_results' => $result->retrievedResults,
                'expected_references' => $result->expectedReferences,
                'hit' => $result->hit,
                'precision' => $result->precision,
                'recall' => $result->recall,
                'reciprocal_rank' => $result->reciprocalRank,
                'execution_time_ms' => $result->executionTimeMs,
            ]);
        }

        $record = RetrievalEvaluationSummaryRecord::query()->create([
            'total_questions' => $summary->totalQuestions,
            'hit_rate' => $summary->hitRate,
            'mean_precision' => $summary->meanPrecision,
            'mean_recall' => $summary->meanRecall,
            'mrr' => $summary->mrr,
            'average_latency_ms' => $summary->averageLatencyMs,
            'configuration' => $summary->configuration,
        ]);

        return new RetrievalEvaluationSummary(
            totalQuestions: $summary->totalQuestions,
            hitRate: $summary->hitRate,
            meanPrecision: $summary->meanPrecision,
            meanRecall: $summary->meanRecall,
            mrr: $summary->mrr,
            averageLatencyMs: $summary->averageLatencyMs,
            configuration: $summary->configuration,
            results: $summary->results,
            summaryId: $record->id,
        );
    }

    /**
     * @param  list<float>  $values
     */
    private function mean(array $values): float
    {
        return $values === [] ? 0.0 : round(array_sum($values) / count($values), 6);
    }

    /**
     * @param  list<string>  $retrievedReferences
     * @param  list<string>  $expectedReferences
     */
    private function firstRelevantRank(array $retrievedReferences, array $expectedReferences): ?int
    {
        foreach ($retrievedReferences as $index => $reference) {
            if (in_array($reference, $expectedReferences, true)) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * @return list<RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData>
     */
    private function retrieve(string $query, int $topK, float $minimumScore, string $strategy): array
    {
        return match ($strategy) {
            'lexical' => $this->lexicalSearch->search($query, $topK),
            'hybrid' => $this->hybridSearch->search($query, $topK, $minimumScore),
            default => $this->semanticSearch->search($query, $topK, $minimumScore),
        };
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique($this->stringList($values)));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function configuration(int $topK, ?float $minimumScore, array $options): array
    {
        return [
            'retrieval' => $options['strategy'] ?? 'vector',
            'embedding_provider' => config('embeddings.provider'),
            'embedding_model' => config('embeddings.model'),
            'embedding_dimensions' => config('embeddings.dimensions'),
            'vector_weight' => config('retrieval.hybrid.vector_weight'),
            'lexical_weight' => config('retrieval.hybrid.lexical_weight'),
            'top_k' => $topK,
            'minimum_score' => $minimumScore,
            'source_filters' => [],
            'question_id' => $options['questionId'] ?? null,
            'category' => $options['category'] ?? null,
            'limit' => $options['limit'] ?? null,
        ];
    }
}
