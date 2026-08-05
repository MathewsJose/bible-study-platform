<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\DTOs\EmbeddingDispatchResult;
use App\Application\Knowledge\DTOs\EmbeddingGenerationResult;
use App\Application\Knowledge\Exceptions\InvalidEmbeddingVectorException;
use App\Jobs\EmbeddingJob;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class EmbeddingGenerationService
{
    public function __construct(
        private EmbeddingProviderInterface $provider,
        private EmbeddingRepositoryInterface $embeddings,
        private EmbeddingVectorValidator $vectors,
    ) {}
    
    /** @param array<string, mixed> $options */
    public function pendingCount(array $options = []): int
    {
        return count($this->embeddingIds($options));
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  null|callable(int): void  $advance
     */
    public function generate(array $options = [], ?callable $advance = null): EmbeddingGenerationResult
    {
        $dispatch = $this->dispatch($options, $advance);

        return new EmbeddingGenerationResult(
            processed: $dispatch->documentsQueued,
            generated: $dispatch->generated,
            failures: $dispatch->failures,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  null|callable(int): void  $advance
     */
    public function dispatch(array $options = [], ?callable $advance = null): EmbeddingDispatchResult
    {
        $ids = $this->embeddingIds($options);
        $batchSize = $this->batchSize($options);
        $chunks = array_chunk($ids, $batchSize);

        if ($chunks === []) {
            return new EmbeddingDispatchResult(0, 0, 0, 0, $this->isSyncQueue());
        }

        $jobs = array_map(
            static fn (array $chunk): EmbeddingJob => new EmbeddingJob(array_values($chunk)),
            $chunks,
        );

        /** @var Batch $batch */
        $batch = Bus::batch($jobs)
            ->name('knowledge-document-embeddings')
            ->allowFailures()
            ->onConnection((string) config('embeddings.queue_connection', config('queue.default')))
            ->dispatch();

        foreach ($chunks as $chunk) {
            if ($advance !== null) {
                $advance(count($chunk));
            }
        }

        $summary = $this->isSyncQueue()
            ? $this->embeddings->summarizeEmbeddingStatus($ids)
            : ['generated' => 0, 'failures' => 0];

        Log::info('Embedding generation batch dispatched.', [
            'batch_id' => $batch->id,
            'documents_queued' => count($ids),
            'jobs_queued' => count($jobs),
            'queue_connection' => config('embeddings.queue_connection', config('queue.default')),
        ]);

        return new EmbeddingDispatchResult(
            documentsQueued: count($ids),
            jobsQueued: count($jobs),
            generated: (int) $summary['generated'],
            failures: (int) $summary['failures'],
            processedSynchronously: $this->isSyncQueue(),
        );
    }

    /**
     * @param  list<string>  $documentIds
     */
    public function processBatch(array $documentIds): EmbeddingGenerationResult
    {
        /** @var Collection<int, KnowledgeDocumentRecord> $documents */
        $documents = $this->embeddings->documentsForEmbedding($documentIds);

        if ($documents->isEmpty()) {
            return new EmbeddingGenerationResult(0, 0, 0);
        }

        $processed = $documents->count();
        $generated = 0;
        $failures = 0;

        try {
            $texts = array_values($documents
                ->map(static fn (KnowledgeDocumentRecord $document): string => $document->content)
                ->values()
                ->all());

            $vectors = $this->provider->embedMany($texts);
        } catch (Throwable $exception) {
            foreach ($documents as $document) {
                $this->failDocument($document->id, $exception);
            }

            return new EmbeddingGenerationResult($processed, 0, $processed);
        }

        foreach ($documents->values() as $index => $document) {
            $vector = $vectors[$index] ?? null;

            try {
                if (! is_array($vector)) {
                    throw new InvalidEmbeddingVectorException('Embedding generation returned no vector.');
                }

                $vector = array_values($vector);
                $this->vectors->validate($vector);
                $this->embeddings->storeEmbedding(
                    documentId: $document->id,
                    embedding: $vector,
                    provider: (string) config('embeddings.provider', 'null'),
                    model: $this->provider->identifier(),
                    dimensions: count($vector),
                );
                $generated++;
            } catch (Throwable $exception) {
                $failures++;
                $this->failDocument($document->id, $exception);
            }
        }

        $result = new EmbeddingGenerationResult($processed, $generated, $failures);

        Log::info('Embedding generation completed.', [
            'processed' => $result->processed,
            'generated' => $result->generated,
            'failures' => $result->failures,
            'batch_size' => $processed,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function embeddingIds(array $options): array
    {
        return $this->embeddings->idsNeedingEmbeddings(
            limit: isset($options['limit']) ? (int) $options['limit'] : null,
            force: (bool) ($options['force'] ?? false),
            retryFailed: (bool) ($options['retryFailed'] ?? false),
            documentId: isset($options['documentId']) ? (string) $options['documentId'] : null,
            sourceType: isset($options['sourceType']) ? (string) $options['sourceType'] : null,
            sourceName: isset($options['sourceName']) ? (string) $options['sourceName'] : null,
        );
    }

    /** @param array<string, mixed> $options */
    private function batchSize(array $options): int
    {
        return max(1, (int) ($options['batch'] ?? config('embeddings.batch_size', 100)));
    }

    private function failDocument(string $documentId, Throwable $exception): void
    {
        $this->embeddings->markEmbeddingFailed($documentId, $exception->getMessage());

        Log::warning('Embedding generation failed for document.', [
            'document_id' => $documentId,
            'exception' => $exception,
        ]);
    }

    private function isSyncQueue(): bool
    {
        return config('embeddings.queue_connection', config('queue.default')) === 'sync';
    }
}
