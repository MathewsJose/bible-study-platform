<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\DoctrinalQueryExpansionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('retrieval:doctrinal-expand
    {--query= : Query text to expand}
    {--mode=combined : Expansion mode: baseline, reference_expansion, lexical_expansion, doctrinal_bridge, combined}
    {--format=table : Output format: table or json}')]
#[Description('Inspect isolated Sprint 32 doctrinal query expansion without running retrieval.')]
final class RetrievalDoctrinalExpandCommand extends Command
{
    public function handle(DoctrinalQueryExpansionService $expansion): int
    {
        $query = trim((string) $this->option('query'));

        if ($query === '') {
            $this->error('The --query option is required.');

            return self::FAILURE;
        }

        try {
            $result = $expansion->expand($query, (string) $this->option('mode'))->toArray();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Sprint 32 Doctrinal Query Expansion');
        $this->line('Mode: '.$result['mode']);
        $this->line('Config: '.$result['config_version']);
        $this->line('Drift: '.$result['query_drift_score']);
        $this->newLine();
        $this->line('Original: '.$result['original_query']);
        $this->line('Expanded: '.$result['expanded_query']);
        $this->newLine();
        $this->table(['Terms'], array_map(static fn (string $term): array => [$term], $result['terms']));

        return self::SUCCESS;
    }
}
