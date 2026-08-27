<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\ContextualIndexBenchmarkService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('evaluate:contextual-index
    {--window=plus_minus_1 : Context window to benchmark}
    {--limit= : Limit evaluated questions for a bounded diagnostic run}
    {--format=table : Output format: table or json}
    {--write-report : Write docs/retrieval-sprint-31-report.md}')]
#[Description('Benchmark the isolated persistent contextual retrieval index without promoting it to production.')]
final class EvaluateContextualIndexCommand extends Command
{
    public function handle(ContextualIndexBenchmarkService $benchmark): int
    {
        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));
        $result = $benchmark->run((string) $this->option('window'), $limit);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->display($result);
        }

        if ((bool) $this->option('write-report')) {
            File::put(base_path('docs/retrieval-sprint-31-report.md'), $this->report($result));
            $this->info('Report written: docs/retrieval-sprint-31-report.md');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function display(array $result): void
    {
        $this->info('Sprint 31 Persistent Contextual Index Benchmark');
        $this->line('Decision: '.$result['decision']);
        $this->line('Dataset: '.$result['dataset']['version']);
        $this->line('Questions: '.$result['dataset']['evaluated_questions'].' / '.$result['dataset']['defined_questions']);
        $this->line('Window: '.$result['index']['window']);
        $this->line('Indexed: '.$result['index']['indexed_documents']);
        $this->line('Embedded: '.$result['index']['embedded_documents']);

        if (isset($result['blocking_reason'])) {
            $this->warn((string) $result['blocking_reason']);
        }

        $this->newLine();
        $this->metricsTable($result['metrics'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function metricsTable(array $metrics): void
    {
        $rows = [];

        foreach ($metrics as $k => $values) {
            $rows[] = [
                strtoupper((string) $k),
                number_format((float) $values['hit_rate'] * 100, 1).'%',
                number_format((float) $values['recall'], 3),
                number_format((float) $values['mrr'], 3),
                number_format((float) ($values['ndcg'] ?? 0.0), 3),
                number_format((float) $values['source_coverage'] * 100, 1).'%',
                $values['latency_ms'].' ms',
            ];
        }

        if ($rows === []) {
            $this->line('No metrics available.');

            return;
        }

        $this->table(['K', 'Hit', 'Recall', 'MRR', 'NDCG', 'Source Coverage', 'Latency'], $rows);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): string
    {
        return implode("\n", [
            '# Sprint 31 - Persistent Contextual Retrieval Index',
            '',
            '## Overall Decision',
            '',
            (string) $result['decision'],
            '',
            '## Scope',
            '',
            'This sprint adds an isolated experimental contextual index. It does not replace production retrieval, embeddings, APIs, agents, MCP, security, or answer generation behavior.',
            '',
            '## Dataset',
            '',
            '- Version: `'.$result['dataset']['version'].'`',
            '- Defined questions: `'.$result['dataset']['defined_questions'].'`',
            '- Evaluated questions: `'.$result['dataset']['evaluated_questions'].'`',
            '',
            '## Index',
            '',
            '- Window: `'.$result['index']['window'].'`',
            '- Indexed documents: `'.$result['index']['indexed_documents'].'`',
            '- Embedded documents: `'.$result['index']['embedded_documents'].'`',
            '- Fingerprint: `'.$result['index']['fingerprint'].'`',
            '',
            '## Metrics',
            '',
            $this->markdownMetrics($result['metrics'] ?? []),
            '',
            '## Category Breakdown',
            '',
            $this->markdownMetrics($result['by_category'] ?? []),
            '',
            '## John 1:1 Diagnostic',
            '',
            'John 1:1 diagnostics are included in JSON command output. They are intentionally summarized here to avoid turning the report into a result dump.',
            '',
            '## Citation Integrity',
            '',
            '- Invalid reference count: `'.($result['citation_integrity']['invalid_reference_count'] ?? 0).'`',
            '',
            '## Commands',
            '',
            '```bash',
            'php artisan retrieval:contextual-index --window=plus_minus_1',
            'php artisan retrieval:contextual-embeddings --window=plus_minus_1',
            'php artisan evaluate:contextual-index --window=plus_minus_1 --write-report',
            '```',
            '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function markdownMetrics(array $metrics): string
    {
        if ($metrics === []) {
            return 'No metrics available.';
        }

        $lines = [
            '| Scope | Questions | Hit Rate | Precision | Recall | MRR | NDCG | Source Coverage | Latency |',
            '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |',
        ];

        foreach ($metrics as $scope => $values) {
            if (! is_array($values) || ! isset($values['questions'])) {
                continue;
            }

            $lines[] = '| '.$scope.' | '
                .$values['questions'].' | '
                .number_format((float) $values['hit_rate'], 3).' | '
                .number_format((float) ($values['precision'] ?? 0.0), 3).' | '
                .number_format((float) $values['recall'], 3).' | '
                .number_format((float) $values['mrr'], 3).' | '
                .number_format((float) ($values['ndcg'] ?? 0.0), 3).' | '
                .number_format((float) $values['source_coverage'], 3).' | '
                .$values['latency_ms'].' ms |';
        }

        return implode("\n", $lines);
    }
}
