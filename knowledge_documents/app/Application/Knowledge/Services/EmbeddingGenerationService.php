<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\DTOs\EmbeddingGenerationResult;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class EmbeddingGenerationService
{
    public const BATCH_SIZE = 100;

    public function __construct(private EmbeddingProviderInterface $provider) {}

    public function pendingCount(): int
    {
        return KnowledgeDocumentRecord::query()
            ->whereNull('embedding')
            ->count();
    }

    /**
     * @param  null|callable(int): void  $advance
     */
    public function generate(?callable $advance = null): EmbeddingGenerationResult
    {
        $processed = 0;
        $generated = 0;
        $failures = 0;
        $failedIds = [];

        while (true) {
            /** @var Collection<int, KnowledgeDocumentRecord> $documents */
            $documents = KnowledgeDocumentRecord::query()
                ->whereNull('embedding')
                ->when($failedIds !== [], fn ($query) => $query->whereNotIn('id', $failedIds))
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($documents->isEmpty()) {
                break;
            }

            $batchSize = $documents->count();
            $processed += $batchSize;

            try {
                $texts = array_values($documents
                    ->map(static fn (KnowledgeDocumentRecord $document): string => $document->content)
                    ->values()
                    ->all());

                $embeddings = $this->provider->embedMany($texts);

                foreach ($documents->values() as $index => $document) {
                    $embedding = $embeddings[$index] ?? null;

                    if ($embedding === null) {
                        $failures++;
                        $failedIds[] = $document->id;
                        Log::warning('Embedding generation returned no vector for document.', [
                            'document_id' => $document->id,
                        ]);

                        continue;
                    }

                    $this->storeEmbedding($document, $embedding);
                    $generated++;
                }
            } catch (Throwable $exception) {
                $failures += $batchSize;
                $failedIds = array_merge($failedIds, $documents->pluck('id')->values()->all());
                Log::warning('Embedding generation batch failed.', [
                    'document_ids' => $documents->pluck('id')->values()->all(),
                    'exception' => $exception,
                ]);
            }

            if ($advance !== null) {
                $advance($batchSize);
            }
        }

        $result = new EmbeddingGenerationResult($processed, $generated, $failures);

        Log::info('Embedding generation completed.', [
            'processed' => $result->processed,
            'generated' => $result->generated,
            'failures' => $result->failures,
            'batch_size' => self::BATCH_SIZE,
        ]);

        return $result;
    }

    /**
     * @param  list<float>  $embedding
     */
    private function storeEmbedding(KnowledgeDocumentRecord $document, array $embedding): void
    {
        KnowledgeDocumentRecord::query()
            ->whereKey($document->id)
            ->update([
                'embedding' => $this->formatEmbedding($embedding),
            ]);
    }

    /**
     * @param  list<float>  $embedding
     */
    private function formatEmbedding(array $embedding): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return '['.implode(',', $embedding).']';
        }

        return json_encode($embedding, JSON_THROW_ON_ERROR);
    }
}
