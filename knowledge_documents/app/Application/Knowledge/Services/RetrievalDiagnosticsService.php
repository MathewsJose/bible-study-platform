<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RetrievalDiagnosticsService
{
    public function __construct(
        private SemanticSearchService $semanticSearch,
        private LexicalSearchService $lexicalSearch,
        private HybridSearchService $hybridSearch,
        private RetrievalEvaluationService $evaluations,
    ) {}

    /** @return EloquentCollection<int, EvaluationQuestionRecord> */
    public function evaluationQuestions(?string $questionId = null): EloquentCollection
    {
        return EvaluationQuestionRecord::query()
            ->when($questionId !== null, fn ($query) => $query->whereKey($questionId))
            ->orderBy('category')
            ->orderBy('question')
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public function evaluationDataset(?string $questionId = null): array
    {
        return $this->evaluationQuestions($questionId)
            ->map(function (EvaluationQuestionRecord $question): array {
                $expectedReferences = $this->strings($question->expected_references ?? []);
                $documentsByReference = KnowledgeDocumentRecord::query()
                    ->whereIn('reference', $expectedReferences)
                    ->get(['reference', 'source_type', 'source_name', 'content'])
                    ->keyBy('reference');

                return [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'category' => $question->category,
                    'expected_references' => $expectedReferences,
                    'expected_source_types' => $this->strings($question->expected_source_types ?? []),
                    'expected_documents' => array_map(
                        function (string $reference) use ($documentsByReference): array {
                            /** @var KnowledgeDocumentRecord|null $document */
                            $document = $documentsByReference->get($reference);

                            return [
                                'reference' => $reference,
                                'exists' => $document !== null,
                                'source_type' => $document?->source_type,
                                'source_name' => $document?->source_name,
                                'content_length' => $document === null ? null : mb_strlen($document->content),
                            ];
                        },
                        $expectedReferences,
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function retrievalResults(EvaluationQuestionRecord $question, int $topK, string $strategy): array
    {
        $strategies = $strategy === 'all' ? ['vector', 'lexical', 'hybrid'] : [$strategy];
        $expectedReferences = $this->strings($question->expected_references ?? []);
        $results = [];

        foreach ($strategies as $strategyName) {
            $ranked = match ($strategyName) {
                'vector' => $this->semanticSearch->search($question->question, $topK, 0.0),
                'lexical' => $this->lexicalSearch->search($question->question, $topK),
                'hybrid' => $this->hybridSearch->search($question->question, $topK, (float) config('retrieval.hybrid.minimum_score', 0.0)),
                default => [],
            };

            $results[$strategyName] = $this->formatRetrievedResults($ranked, $expectedReferences, $strategyName);
        }

        return $results;
    }

    /** @return array<string, mixed> */
    public function knowledgeBaseStats(): array
    {
        return [
            'total' => KnowledgeDocumentRecord::query()->count(),
            'by_source_type' => $this->countsBy('source_type'),
            'by_source_name' => $this->countsBy('source_name'),
            'by_tradition' => $this->countsBy('tradition'),
            'embeddings' => $this->embeddingStats(),
            'content' => $this->contentStats(),
            'chunking' => $this->chunkingStats(),
            'indexes' => $this->indexStats(),
        ];
    }

    /** @return array<string, mixed> */
    public function embeddingStats(): array
    {
        $total = KnowledgeDocumentRecord::query()->count();
        $withEmbeddings = KnowledgeDocumentRecord::query()->whereNotNull('embedding')->count();
        $models = KnowledgeDocumentRecord::query()
            ->whereNotNull('embedding')
            ->select('embedding_model')
            ->selectRaw('count(*) as total')
            ->groupBy('embedding_model')
            ->orderBy('embedding_model')
            ->get()
            ->mapWithKeys(static fn (KnowledgeDocumentRecord $record): array => [
                (string) ($record->embedding_model ?? 'null') => (int) $record->getAttribute('total'),
            ])
            ->all();
        $providers = KnowledgeDocumentRecord::query()
            ->whereNotNull('embedding')
            ->select('embedding_provider')
            ->selectRaw('count(*) as total')
            ->groupBy('embedding_provider')
            ->orderBy('embedding_provider')
            ->get()
            ->mapWithKeys(static fn (KnowledgeDocumentRecord $record): array => [
                (string) ($record->embedding_provider ?? 'null') => (int) $record->getAttribute('total'),
            ])
            ->all();
        $storedDimensions = KnowledgeDocumentRecord::query()
            ->whereNotNull('embedding')
            ->select('embedding_dimensions')
            ->selectRaw('count(*) as total')
            ->groupBy('embedding_dimensions')
            ->orderBy('embedding_dimensions')
            ->get()
            ->mapWithKeys(static fn (KnowledgeDocumentRecord $record): array => [
                (string) ($record->embedding_dimensions ?? 'null') => (int) $record->getAttribute('total'),
            ])
            ->all();

        return [
            'total' => $total,
            'with_embeddings' => $withEmbeddings,
            'without_embeddings' => $total - $withEmbeddings,
            'coverage' => $total === 0 ? 0.0 : round($withEmbeddings / $total, 6),
            'configured_model' => config('embeddings.model'),
            'configured_dimensions' => (int) config('embeddings.dimensions'),
            'actual_dimensions' => $this->actualEmbeddingDimensions(),
            'models' => $models,
            'providers' => $providers,
            'stored_dimensions' => $storedDimensions,
        ];
    }

    /** @return array<string, mixed> */
    public function queryEmbeddingStats(string $query): array
    {
        $provider = app(EmbeddingProviderInterface::class);
        $embedding = $provider->embed($query);

        return [
            'provider' => $provider->identifier(),
            'configured_model' => config('embeddings.model'),
            'configured_dimensions' => (int) config('embeddings.dimensions'),
            'actual_dimensions' => count($embedding),
        ];
    }

    /** @return array<string, mixed> */
    public function scoreRanges(EvaluationQuestionRecord $question, int $topK): array
    {
        $results = $this->retrievalResults($question, $topK, 'all');

        return [
            'vector_raw' => $this->range(array_column($results['vector'], 'score')),
            'lexical_raw' => $this->range(array_column($results['lexical'], 'score')),
            'hybrid_vector_normalized' => $this->range(array_column($results['hybrid'], 'similarity_score')),
            'hybrid_lexical_normalized' => $this->range(array_column($results['hybrid'], 'lexical_score')),
            'hybrid_combined' => $this->range(array_column($results['hybrid'], 'combined_score')),
        ];
    }

    /** @return array<string, mixed> */
    public function searchImplementation(): array
    {
        return [
            'vector' => [
                'operator' => '<=>',
                'metric' => 'cosine distance',
                'similarity' => '1 - (embedding <=> query_vector)',
                'ordering' => 'Laravel whereVectorSimilarTo orders by distance and applies threshold',
                'filters' => ['source_types', 'source_type', 'tradition', 'source_name'],
                'limit' => 'top_k',
            ],
            'lexical' => [
                'tsvector' => 'generated search_vector: reference A, title A, source_name B, content C',
                'tsquery' => "websearch_to_tsquery('english', query)",
                'ranking' => 'ts_rank_cd(search_vector, tsquery) plus exact reference/source_name boosts',
                'index' => 'knowledge_documents_search_vector_gin',
                'filters' => ['source_type', 'source_types', 'source_name', 'tradition', 'book', 'chapter'],
                'limit' => 'limit',
            ],
            'hybrid' => [
                'normalization' => 'score / max positive score within each candidate list',
                'vector_weight' => (float) config('retrieval.hybrid.vector_weight', 0.70),
                'lexical_weight' => (float) config('retrieval.hybrid.lexical_weight', 0.30),
                'fetch_multiplier' => (int) config('retrieval.hybrid.fetch_multiplier', 3),
            ],
        ];
    }

    /** @return array<string, \App\Application\Knowledge\DTOs\RetrievalEvaluationSummary> */
    public function comparison(int $topK = 5): array
    {
        return $this->evaluations->compare(['topK' => $topK, 'minimumScore' => 0.0]);
    }

    /** @return list<string> */
    public function potentialProblems(): array
    {
        $problems = [];
        $stats = $this->knowledgeBaseStats();
        $embeddings = $stats['embeddings'];
        $content = $stats['content'];
        $comparison = $this->comparison();

        if (($embeddings['coverage'] ?? 0.0) < 0.95) {
            $problems[] = 'Embedding coverage is incomplete; vector and hybrid search cannot retrieve documents without embeddings.';
        }

        if (count($embeddings['actual_dimensions'] ?? []) > 1) {
            $problems[] = 'Documents contain multiple embedding dimensions.';
        }

        if (count($embeddings['models'] ?? []) > 1) {
            $problems[] = 'Documents were embedded with more than one embedding model.';
        }

        if (count($embeddings['providers'] ?? []) > 1) {
            $problems[] = 'Documents were embedded with more than one provider.';
        }

        if (count($embeddings['stored_dimensions'] ?? []) > 1) {
            $problems[] = 'Documents report more than one stored embedding dimension.';
        }

        if (($content['empty'] ?? 0) > 0) {
            $problems[] = 'Some documents have empty content.';
        }

        if (($content['very_short'] ?? 0) > 0) {
            $problems[] = 'Some chunks are very short, which can weaken semantic retrieval context.';
        }

        if (($comparison['lexical']->hitRate ?? 0.0) > ($comparison['vector']->hitRate ?? 0.0)) {
            $problems[] = 'Lexical search is outperforming vector search on Hit@5, suggesting exact terms/references currently carry more signal than embeddings.';
        }

        if (($comparison['hybrid']->meanPrecision ?? 0.0) < ($comparison['lexical']->meanPrecision ?? 0.0)) {
            $problems[] = 'Hybrid precision is below lexical precision, suggesting fusion is admitting semantically similar but non-expected candidates.';
        }

        return $problems === [] ? ['No obvious retrieval health problems were detected by automated checks.'] : $problems;
    }

    /**
     * @param  list<RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData>  $ranked
     * @param  list<string>  $expectedReferences
     * @return list<array<string, mixed>>
     */
    private function formatRetrievedResults(array $ranked, array $expectedReferences, string $strategy): array
    {
        return array_map(
            static function (RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData $result, int $index) use ($expectedReferences, $strategy): array {
                $vectorScore = null;
                $lexicalScore = null;
                $combinedScore = null;

                if ($result instanceof HybridRankedKnowledgeDocumentData) {
                    $vectorScore = $result->scoreBreakdown['vector'] ?? 0.0;
                    $lexicalScore = $result->scoreBreakdown['lexical'] ?? 0.0;
                    $combinedScore = $result->score;
                } elseif ($strategy === 'vector') {
                    $vectorScore = $result->score;
                } elseif ($strategy === 'lexical') {
                    $lexicalScore = $result->score;
                }

                return [
                    'rank' => $index + 1,
                    'reference' => $result->document->reference,
                    'source_type' => $result->document->sourceType,
                    'source_name' => $result->document->sourceName,
                    'score' => $result->score,
                    'similarity_score' => $vectorScore,
                    'lexical_score' => $lexicalScore,
                    'combined_score' => $combinedScore,
                    'expected' => in_array($result->document->reference, $expectedReferences, true),
                ];
            },
            $ranked,
            array_keys($ranked),
        );
    }

    /** @return array<string, int> */
    private function countsBy(string $column): array
    {
        return KnowledgeDocumentRecord::query()
            ->select($column)
            ->selectRaw('count(*) as total')
            ->groupBy($column)
            ->orderBy($column)
            ->get()
            ->mapWithKeys(static fn (KnowledgeDocumentRecord $record): array => [
                (string) $record->getAttribute($column) => (int) $record->getAttribute('total'),
            ])
            ->all();
    }

    /** @return array<string, int> */
    private function contentStats(): array
    {
        return [
            'empty' => KnowledgeDocumentRecord::query()->whereRaw("length(trim(coalesce(content, ''))) = 0")->count(),
            'very_short' => KnowledgeDocumentRecord::query()->whereRaw("length(trim(coalesce(content, ''))) > 0 and length(content) < 80")->count(),
            'very_long' => KnowledgeDocumentRecord::query()->whereRaw('length(content) > 5000')->count(),
        ];
    }

    /** @return array<string, array<string, int|float|null>> */
    private function chunkingStats(): array
    {
        return KnowledgeDocumentRecord::query()
            ->select('source_type')
            ->selectRaw('min(length(content)) as min_length')
            ->selectRaw('max(length(content)) as max_length')
            ->selectRaw('avg(length(content)) as avg_length')
            ->selectRaw("sum(case when length(trim(coalesce(content, ''))) > 0 and length(content) < 80 then 1 else 0 end) as very_short")
            ->selectRaw('sum(case when length(content) > 5000 then 1 else 0 end) as very_long')
            ->groupBy('source_type')
            ->orderBy('source_type')
            ->get()
            ->mapWithKeys(function (KnowledgeDocumentRecord $record): array {
                $lengths = KnowledgeDocumentRecord::query()
                    ->where('source_type', $record->source_type)
                    ->selectRaw('length(content) as content_length')
                    ->pluck('content_length')
                    ->map(static fn (mixed $length): int => (int) $length)
                    ->sort()
                    ->values();

                return [
                    $record->source_type => [
                        'min' => (int) $record->getAttribute('min_length'),
                        'max' => (int) $record->getAttribute('max_length'),
                        'avg' => round((float) $record->getAttribute('avg_length'), 2),
                        'median' => $this->median($lengths),
                        'very_short' => (int) $record->getAttribute('very_short'),
                        'very_long' => (int) $record->getAttribute('very_long'),
                    ],
                ];
            })
            ->all();
    }

    /** @return list<int> */
    private function actualEmbeddingDimensions(): array
    {
        if (DB::getDriverName() === 'pgsql') {
            return collect(DB::select('select distinct vector_dims(embedding) as dimensions from knowledge_documents where embedding is not null order by dimensions'))
                ->map(static fn (object $row): int => (int) $row->dimensions)
                ->all();
        }

        return KnowledgeDocumentRecord::query()
            ->whereNotNull('embedding')
            ->get(['embedding'])
            ->map(static fn (KnowledgeDocumentRecord $record): int => is_array($record->embedding) ? count($record->embedding) : 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function indexStats(): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return [
                'driver' => DB::getDriverName(),
                'vector_index' => null,
                'lexical_index' => null,
            ];
        }

        $indexes = collect(DB::select(
            "select indexname, indexdef from pg_indexes where tablename = 'knowledge_documents' order by indexname",
        ))->mapWithKeys(static fn (object $row): array => [
            (string) $row->indexname => (string) $row->indexdef,
        ])->all();

        return [
            'driver' => 'pgsql',
            'vector_index' => $indexes['knowledge_documents_embedding_hnsw'] ?? $indexes['knowledge_documents_embedding_hnsw_index'] ?? null,
            'lexical_index' => $indexes['knowledge_documents_search_vector_gin'] ?? null,
            'all' => $indexes,
        ];
    }

    /** @param  Collection<int, int>  $values */
    private function median(Collection $values): ?float
    {
        $count = $values->count();

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 2);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array{min: float|null, max: float|null}
     */
    private function range(array $values): array
    {
        $numbers = array_values(array_map(static fn (mixed $value): float => (float) $value, array_filter($values, static fn (mixed $value): bool => is_numeric($value))));

        if ($numbers === []) {
            return ['min' => null, 'max' => null];
        }

        return ['min' => min($numbers), 'max' => max($numbers)];
    }
}
