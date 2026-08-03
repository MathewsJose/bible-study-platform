<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Knowledge\Services\EmbeddingGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmbeddingJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  list<string>  $documentIds
     */
    public function __construct(public readonly array $documentIds) {}

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(EmbeddingGenerationService $embeddings): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $result = $embeddings->processBatch($this->documentIds);

        Log::info('Embedding job completed.', [
            'documents' => count($this->documentIds),
            'processed' => $result->processed,
            'generated' => $result->generated,
            'failures' => $result->failures,
        ]);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Embedding job failed permanently.', [
            'document_ids' => $this->documentIds,
            'exception' => $exception,
        ]);
    }
}
