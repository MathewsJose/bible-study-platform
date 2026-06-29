<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Services\EmbeddingGenerationService;
use Illuminate\Console\Command;

final class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'embeddings:generate';

    protected $description = 'Generate embeddings for knowledge documents that do not have vectors yet.';

    public function handle(EmbeddingGenerationService $embeddings): int
    {
        $pending = $embeddings->pendingCount();

        if ($pending === 0) {
            $this->info('No knowledge documents need embeddings.');

            return self::SUCCESS;
        }

        $this->info("Generating embeddings for {$pending} knowledge documents.");

        $bar = $this->output->createProgressBar($pending);
        $bar->start();

        $result = $embeddings->generate(static function (int $count) use ($bar): void {
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
