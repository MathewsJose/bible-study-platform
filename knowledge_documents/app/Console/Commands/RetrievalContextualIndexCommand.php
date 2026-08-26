<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\ContextualRetrievalIndexService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('retrieval:contextual-index
    {--window=plus_minus_1 : Context window: verse, plus_minus_1, plus_minus_3, chapter}
    {--batch=25 : Number of source documents per batch}
    {--force : Rebuild even when the stored checksum is unchanged}
    {--source-type=bible_verse : Source type to index}
    {--dry-run : Report changes without writing contextual records}
    {--limit= : Optional bounded smoke-test limit}
    {--format=table : Output format: table or json}')]
#[Description('Build the isolated experimental persistent contextual retrieval index.')]
final class RetrievalContextualIndexCommand extends Command
{
    public function handle(ContextualRetrievalIndexService $index): int
    {
        $result = $index->build([
            'window' => (string) $this->option('window'),
            'batch' => (int) $this->option('batch'),
            'force' => (bool) $this->option('force'),
            'source_type' => (string) $this->option('source-type'),
            'dry_run' => (bool) $this->option('dry-run'),
            'limit' => $this->option('limit') === null ? null : (int) $this->option('limit'),
        ]);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Contextual Retrieval Index');
        $this->table(
            ['Window', 'Processed', 'Created', 'Updated', 'Skipped', 'Failed', 'Elapsed', 'Dry Run'],
            [[
                $result['window'],
                $result['processed'],
                $result['created'],
                $result['updated'],
                $result['skipped'],
                $result['failed'],
                $result['elapsed_ms'].' ms',
                $result['dry_run'] ? 'yes' : 'no',
            ]],
        );

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
