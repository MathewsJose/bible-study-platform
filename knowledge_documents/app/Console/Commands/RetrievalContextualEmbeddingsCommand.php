<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\ContextualRetrievalEmbeddingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retrieval:contextual-embeddings
    {--window= : Restrict generation to one context window}
    {--batch=25 : Number of contextual documents per embedding batch}
    {--force : Regenerate embeddings even when already present}
    {--dry-run : Report work without calling the provider or writing embeddings}
    {--limit= : Optional bounded smoke-test limit}
    {--format=table : Output format: table or json}')]
#[Description('Generate embeddings for the isolated experimental contextual retrieval index.')]
final class RetrievalContextualEmbeddingsCommand extends Command
{
    public function handle(ContextualRetrievalEmbeddingService $embeddings): int
    {
        $window = $this->option('window');
        $result = $embeddings->generate([
            'window' => $window === null || $window === '' ? null : (string) $window,
            'batch' => (int) $this->option('batch'),
            'force' => (bool) $this->option('force'),
            'dry_run' => (bool) $this->option('dry-run'),
            'limit' => $this->option('limit') === null ? null : (int) $this->option('limit'),
        ]);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Contextual Retrieval Embeddings');
        $this->table(
            ['Window', 'Processed', 'Embedded', 'Skipped', 'Failed', 'Elapsed', 'Dry Run'],
            [[
                $result['window'] ?? 'all',
                $result['processed'],
                $result['embedded'],
                $result['skipped'],
                $result['failed'],
                $result['elapsed_ms'].' ms',
                $result['dry_run'] ? 'yes' : 'no',
            ]],
        );

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
