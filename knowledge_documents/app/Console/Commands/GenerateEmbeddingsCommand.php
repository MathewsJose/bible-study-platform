<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Services\EmbeddingGenerationService;
use Illuminate\Console\Command;

final class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'embeddings:generate 
                            {--retry-failed : Process documents with failed status as well}
                            {--limit= : Limit the number of documents to process}
                            {--source-type= : Filter by source type}
                            {--source-name= : Filter by source name}
                            {--dry-run : Only show how many documents would be processed}';

    protected $description = 'Generate embeddings for knowledge documents that need them.';

    public function handle(EmbeddingGenerationService $embeddings): int
    {
        $options = [
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

        $result = $embeddings->generate($options, static function (int $count) use ($bar): void {
            $bar->advance($count);
        });

        $bar->finish();
        $this->newLine(2);

        $this->line("documents processed: {$result->processed}");
        $this->line("embeddings generated: {$result->generated}");
        $this->line("failures: {$result->failures}");

        return $result->failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
