<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\DTOs\EmbeddingGenerationResult;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class EmbeddingGenerationService
{
    public const BATCH_SIZE = 100;

    public function __construct(private EmbeddingProviderInterface $provider) {}
    
    /** @param array<string, mixed> $options */
    public function pendingCount(array $options = []): int
    {
        return $this->buildQuery($options)->count();
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  null|callable(int): void  $advance
     */
    public function generate(array $options = [], ?callable $advance = null): EmbeddingGenerationResult
    {
        $processed = 0;
        $generated = 0;
        $failures = 0;
        $failedIds = [];
        
        $limit = isset($options['limit']) ? (int) $options['limit'] : null;
        $dryRun = (bool) ($options['dryRun'] ?? false);

        if ($dryRun) {
            $count = $this->pendingCount($options);
            return new EmbeddingGenerationResult($count, 0, 0);
        }

        while (true) {
            $batchLimit = self::BATCH_SIZE;
            if ($limit !== null) {
                $batchLimit = min(self::BATCH_SIZE, $limit - $processed);
            }

            if ($batchLimit <= 0) {
                break;
            }

            /** @var Collection<int, KnowledgeDocumentRecord> $documents */
            $documents = $this->buildQuery($options)
                ->when($failedIds !== [], fn ($query) => $query->whereNotIn('id', $failedIds))
                ->orderBy('id')
                ->limit($batchLimit)
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
                        $this->markAsFailed($document, 'Embedding generation returned no vector.');
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

                foreach ($documents as $document) {
                    $this->markAsFailed($document, $exception->getMessage());
                }

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

    private function buildQuery(array $options): \Illuminate\Database\Eloquent\Builder
    {
        $query = KnowledgeDocumentRecord::query();

        if ($options['retryFailed'] ?? false) {
            $query->whereIn('embedding_status', [EmbeddingStatus::Pending, EmbeddingStatus::Failed]);
        } else {
            $query->where('embedding_status', EmbeddingStatus::Pending);
        }

        if (isset($options['sourceType'])) {
            $query->where('source_type', $options['sourceType']);
        }

        if (isset($options['sourceName'])) {
            $query->where('source_name', $options['sourceName']);
        }

        return $query;
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
                'embedding_status' => EmbeddingStatus::Ready,
                'embedding_model' => $this->provider->identifier(),
                'embedded_at' => now(),
                'embedding_error' => null,
            ]);
    }

    private function markAsFailed(KnowledgeDocumentRecord $document, string $error): void
    {
        KnowledgeDocumentRecord::query()
            ->whereKey($document->id)
            ->update([
                'embedding_status' => EmbeddingStatus::Failed,
                'embedding_error' => $error,
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
