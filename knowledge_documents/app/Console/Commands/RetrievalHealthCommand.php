<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Services\RetrievalDiagnosticsService;
use Illuminate\Console\Command;

final class RetrievalHealthCommand extends Command
{
    protected $signature = 'retrieval:health {--top-k=5 : Top K value for evaluation metrics}';

    protected $description = 'Report retrieval health, data coverage, index status, and likely retrieval quality issues.';

    public function handle(RetrievalDiagnosticsService $diagnostics): int
    {
        $topK = max(1, (int) $this->option('top-k'));
        $stats = $diagnostics->knowledgeBaseStats();
        $implementation = $diagnostics->searchImplementation();
        $comparison = $diagnostics->comparison($topK);

        $this->info('Knowledge Documents');
        $this->line('Total: '.$stats['total']);
        $this->table(['Source Type', 'Count'], $this->rows($stats['by_source_type']));
        $this->table(['Source Name', 'Count'], $this->rows($stats['by_source_name']));
        $this->table(['Tradition', 'Count'], $this->rows($stats['by_tradition']));

        $this->newLine();
        $this->info('Embeddings');
        $embeddings = $stats['embeddings'];
        $this->line('Total: '.$embeddings['total']);
        $this->line('With embeddings: '.$embeddings['with_embeddings']);
        $this->line('Without embeddings: '.$embeddings['without_embeddings']);
        $this->line('Coverage: '.number_format((float) $embeddings['coverage'] * 100, 2).'%');
        $this->line('Configured model: '.$embeddings['configured_model']);
        $this->line('Configured dimensions: '.$embeddings['configured_dimensions']);
        $this->line('Actual dimensions: '.$this->csv($embeddings['actual_dimensions']));
        $this->table(['Embedding Provider', 'Count'], $this->rows($embeddings['providers']));
        $this->table(['Embedding Model', 'Count'], $this->rows($embeddings['models']));
        $this->table(['Stored Dimensions', 'Count'], $this->rows($embeddings['stored_dimensions']));

        $this->newLine();
        $this->info('Content');
        $content = $stats['content'];
        $this->line('Empty: '.$content['empty']);
        $this->line('Very short (<80 chars): '.$content['very_short']);
        $this->line('Very long (>5000 chars): '.$content['very_long']);

        $this->newLine();
        $this->info('Chunking');
        $this->table(
            ['Source Type', 'Min', 'Max', 'Average', 'Median', 'Very Short', 'Very Long'],
            array_map(static fn (string $sourceType, array $row): array => [
                $sourceType,
                $row['min'],
                $row['max'],
                $row['avg'],
                $row['median'] ?? '-',
                $row['very_short'],
                $row['very_long'],
            ], array_keys($stats['chunking']), array_values($stats['chunking'])),
        );

        $this->newLine();
        $this->info('Vector Search');
        $this->line('Embedding model: '.$embeddings['configured_model']);
        $this->line('Dimensions: '.$embeddings['configured_dimensions']);
        $this->line('Distance metric: '.$implementation['vector']['metric']);
        $this->line('Operator: '.$implementation['vector']['operator']);
        $this->line('Similarity: '.$implementation['vector']['similarity']);
        $this->line('Index: '.($stats['indexes']['vector_index'] ?? 'not detected'));

        $this->newLine();
        $this->info('Lexical Search');
        $this->line('GIN index: '.($stats['indexes']['lexical_index'] ?? 'not detected'));
        $this->line('Search configuration: english');
        $this->line('tsvector: '.$implementation['lexical']['tsvector']);
        $this->line('tsquery: '.$implementation['lexical']['tsquery']);

        $this->newLine();
        $this->info('Evaluation');
        $this->table(
            ['Strategy', "Hit@{$topK}", "Precision@{$topK}", "Recall@{$topK}", 'MRR', 'Avg Latency'],
            array_map(static fn (string $strategy, $summary): array => [
                $strategy,
                number_format($summary->hitRate * 100, 1).'%',
                number_format($summary->meanPrecision, 3),
                number_format($summary->meanRecall, 3),
                number_format($summary->mrr, 3),
                $summary->averageLatencyMs.' ms',
            ], array_keys($comparison), array_values($comparison)),
        );

        $this->newLine();
        $this->info('Potential Problems');
        foreach ($diagnostics->potentialProblems() as $problem) {
            $this->line('- '.$problem);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $values
     * @return list<array{0: string, 1: int}>
     */
    private function rows(array $values): array
    {
        return array_map(static fn (string $name, int $count): array => [$name, $count], array_keys($values), array_values($values));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function csv(array $values): string
    {
        $strings = array_values(array_filter(array_map(static fn (mixed $value): string => (string) $value, $values)));

        return $strings === [] ? 'none' : implode(', ', $strings);
    }
}
