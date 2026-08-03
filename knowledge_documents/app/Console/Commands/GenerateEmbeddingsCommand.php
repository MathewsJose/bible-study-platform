<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Services\EmbeddingGenerationService;
use Illuminate\Console\Command;

final class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'embeddings:generate 
                            {--batch= : Number of documents per queue job}
                            {--force : Regenerate embeddings even when documents already have vectors}
                            {--document-id= : Generate an embedding for a single knowledge document}
                            {--retry-failed : Process documents with failed status as well}
                            {--limit= : Limit the number of documents to process}
                            {--source-type= : Filter by source type}
                            {--source-name= : Filter by source name}
                            {--dry-run : Only show how many documents would be processed}';

    protected $description = 'Generate embeddings for knowledge documents that need them.';

    public function handle(EmbeddingGenerationService $embeddings): int
    {
        $options = [
            'batch' => $this->option('batch'),
            'force' => $this->option('force'),
            'documentId' => $this->option('document-id'),
            'retryFailed' => $this->option('retry-failed'),
            'limit' => $this->option('limit'),
            'sourceType' => $this->option('source-type'),
            'sourceName' => $this->option('source-name'),
            'dryRun' => $this->option('dry-run'),
        ];

        $pending = $embeddings->pendingCount($options);

        if ($pending === 0) {
            $this->info('No knowledge documents need embeddings.');

            return self::SUCCESS;
        }

        if ($options['dryRun']) {
            $this->info("Dry run: {$pending} knowledge documents would be processed.");
            return self::SUCCESS;
        }

        $this->info("Generating embeddings for {$pending} knowledge documents.");

        $bar = $this->output->createProgressBar($pending);
        $bar->start();

        $result = $embeddings->dispatch($options, static function (int $count) use ($bar): void {
            $bar->advance($count);
        });

        $bar->finish();
        $this->newLine(2);

        $this->line("documents queued: {$result->documentsQueued}");
        $this->line("jobs queued: {$result->jobsQueued}");
        $this->line("embeddings generated: {$result->generated}");
        $this->line("failures: {$result->failures}");
        $this->line('queue connection: '.config('embeddings.queue_connection', config('queue.default')));

        if (! $result->processedSynchronously) {
            $this->line('Embedding jobs were dispatched. Run a queue worker to process them.');
        }

        return $result->failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
