<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\ScriptureRoutingSearchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('retrieval:scripture-route
    {--query= : Query text to route}
    {--mode=hybrid_router : Routing mode}
    {--limit=10 : Number of ranked results}
    {--source-name= : Optional explicit source_name override for exact references}
    {--format=table : Output format: table or json}')]
#[Description('Inspect isolated Sprint 33 deterministic Scripture routing for one query.')]
final class RetrievalScriptureRouteCommand extends Command
{
    public function handle(ScriptureRoutingSearchService $search): int
    {
        $query = trim((string) $this->option('query'));

        if ($query === '') {
            $this->error('The --query option is required.');

            return self::FAILURE;
        }

        try {
            $result = $search->search(
                query: $query,
                mode: (string) $this->option('mode'),
                limit: max(1, (int) $this->option('limit')),
                sourceName: $this->option('source-name') === null ? null : (string) $this->option('source-name'),
            )->toArray();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('Sprint 33 Scripture Routing');
        $this->line('Mode: '.$result['mode']);
        $this->line('Route: '.$result['classification']['route']);
        $this->line('References: '.implode(', ', $result['classification']['references']));
        $this->newLine();

        $this->table(
            ['Rank', 'Reference', 'Source', 'Type', 'Score', 'Origin'],
            array_map(static fn (array $row): array => [
                $row['rank'],
                $row['reference'],
                $row['source_name'],
                $row['source_type'],
                number_format((float) $row['score'], 3),
                $row['retrieval_origin'],
            ], $result['results']),
        );

        return self::SUCCESS;
    }
}
