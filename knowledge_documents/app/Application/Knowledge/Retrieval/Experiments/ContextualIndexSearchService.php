<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Services\EmbeddingVectorValidator;
use App\Infrastructure\Knowledge\Persistence\RetrievalContextualDocumentRecord;
use Illuminate\Support\Facades\DB;

final readonly class ContextualIndexSearchService
{
    public function __construct(
        private EmbeddingProviderInterface $embeddings,
        private EmbeddingVectorValidator $validator,
        private ContextualRetrievalIndexService $index,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, string $window, int $topK): array
    {
        $embedding = $this->embeddings->embed($query);
        $this->validator->validate($embedding);
        $window = $this->index->window($window);

        if (DB::getDriverName() === 'pgsql') {
            return $this->postgresSearch($embedding, $window, $topK);
        }

        return $this->inMemorySearch($embedding, $window, $topK);
    }

    /**
     * @param  list<float>  $embedding
     * @return list<array<string, mixed>>
     */
    private function postgresSearch(array $embedding, string $window, int $topK): array
    {
        $vector = '['.implode(',', $embedding).']';

        $results = RetrievalContextualDocumentRecord::query()
            ->where('context_window', $window)
            ->whereNotNull('embedding')
            ->select('*')
            ->selectRaw('1 - (embedding <=> ?::vector) as similarity', [$vector])
            ->orderByRaw('embedding <=> ?::vector', [$vector])
            ->limit(max(1, $topK))
            ->get()
            ->map(fn (RetrievalContextualDocumentRecord $record): array => $this->row($record, (float) $record->getAttribute('similarity')))
            ->values()
            ->all();

        return array_values($results);
    }

    /**
     * @param  list<float>  $embedding
     * @return list<array<string, mixed>>
     */
    private function inMemorySearch(array $embedding, string $window, int $topK): array
    {
        $ranked = RetrievalContextualDocumentRecord::query()
            ->where('context_window', $window)
            ->whereNotNull('embedding')
            ->get()
            ->map(fn (RetrievalContextualDocumentRecord $record): array => $this->row($record, $this->cosine($embedding, $this->storedEmbedding($record))))
            ->sortByDesc('score')
            ->values()
            ->take(max(1, $topK))
            ->all();

        return array_values($ranked);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(RetrievalContextualDocumentRecord $record, float $score): array
    {
        return [
            'id' => $record->source_document_id,
            'contextual_id' => $record->id,
            'reference' => $record->reference,
            'source_name' => $record->source_name,
            'source_type' => $record->source_type,
            'book' => $record->book,
            'chapter' => $record->chapter,
            'verse' => $record->verse,
            'document_type' => $record->document_type,
            'context_window' => $record->context_window,
            'score' => round($score, 6),
        ];
    }

    /**
     * @return list<float>
     */
    private function storedEmbedding(RetrievalContextualDocumentRecord $record): array
    {
        $embedding = $record->embedding;

        if (is_string($embedding)) {
            $decoded = json_decode($embedding, true);

            return is_array($decoded) ? array_map('floatval', array_values($decoded)) : [];
        }

        return is_array($embedding) ? array_map('floatval', $embedding) : [];
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

        return $dot / (sqrt($firstMagnitude) * sqrt($secondMagnitude));
    }
}
