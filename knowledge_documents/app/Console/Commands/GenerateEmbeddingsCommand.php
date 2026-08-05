<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Services\EmbeddingGenerationService;
use Illuminate\Console\Command;

final class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'embeddings 
                            {--batch= : Number of documents per queue job}
                            {--force : Regenerate embeddings even when documents already have vectors}
                            {--document-id= : Generate an embedding for a single knowledge document}
                            {--retry-failed : Process documents with failed status as well}
                            {--limit= : Limit the number of documents to process}
                            {--source-type= : Filter by source type}
                            {--source-name= : Filter by source name}
                            {--dry-run : Only show how many documents would be processed}';

    /** @var list<string> */
    protected $aliases = ['embeddings:generate'];

    protected $description = 'Generate embeddings for knowledge documents that need them.';

    public function handle(EmbeddingGenerationService $embeddings): int
    {
        $startedAt = microtime(true);

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
        $this->displayConfiguration();

        if ($pending === 0) {
            $this->info('No knowledge documents need embeddings.');
            $this->line('total candidates: 0');
            $this->line('processed: 0');
            $this->line('succeeded: 0');
            $this->line('skipped: 0');
            $this->line('failed: 0');
            $this->line('duration: '.$this->duration($startedAt));

            return self::SUCCESS;
        }

        if ($options['dryRun']) {
            $this->info("Dry run: {$pending} knowledge documents would be processed.");
            $this->line('No embedding API request was made and no database rows were modified.');
            $this->line("total candidates: {$pending}");
            $this->line("Total candidates: {$pending}");
            $this->line('processed: 0');
            $this->line('Processed: 0');
            $this->line('succeeded: 0');
            $this->line('Successful: 0');
            $this->line("skipped: {$pending}");
            $this->line("Skipped: {$pending}");
            $this->line('failed: 0');
            $this->line('Failed: 0');
            $this->line('duration: '.$this->duration($startedAt));
            $this->line('Duration: '.$this->duration($startedAt));

            return self::SUCCESS;
        }

        $this->info("Generating embeddings for {$pending} knowledge documents.");
        $this->info('Embedding documents...');

        $bar = $this->output->createProgressBar($pending);
        $bar->start();

        $result = $embeddings->dispatch($options, static function (int $count) use ($bar): void {
            $bar->advance($count);
        });

        $bar->finish();
        $this->newLine(2);

        $skipped = max(0, $pending - $result->documentsQueued);

        $this->line("total candidates: {$pending}");
        $this->line("Total candidates: {$pending}");
        $this->line("processed: {$result->documentsQueued}");
        $this->line("Processed: {$result->documentsQueued}");
        $this->line("succeeded: {$result->generated}");
        $this->line("Successful: {$result->generated}");
        $this->line("skipped: {$skipped}");
        $this->line("Skipped: {$skipped}");
        $this->line("failed: {$result->failures}");
        $this->line("Failed: {$result->failures}");
        $this->line('duration: '.$this->duration($startedAt));
        $this->line('Duration: '.$this->duration($startedAt));
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

    private function duration(float $startedAt): string
    {
        return number_format(microtime(true) - $startedAt, 2).'s';
    }

    private function displayConfiguration(): void
    {
        $this->line('Provider: '.config('embeddings.provider'));
        $this->line('Model: '.config('embeddings.model'));
        $this->line('Dimensions: '.config('embeddings.dimensions'));
        $this->line('Batch size: '.($this->option('batch') ?? config('embeddings.batch_size', 100)));
        $this->line('Queue connection: '.config('embeddings.queue_connection', config('queue.default')));
    }
}
