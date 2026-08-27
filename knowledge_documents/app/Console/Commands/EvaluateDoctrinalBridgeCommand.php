<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\DoctrinalBridgeExperimentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

#[Signature('evaluate:doctrinal-bridge
    {--mode=all : baseline, reference_expansion, lexical_expansion, doctrinal_bridge, combined, or all}
    {--limit= : Limit evaluated questions for bounded diagnostics}
    {--category= : Evaluate only one Sprint 30 category}
    {--question-id= : Evaluate one question by 1-based number or qNNN id}
    {--format=table : Output format: table or json}
    {--write-report : Write docs/retrieval-sprint-32-report.md}')]
#[Description('Run isolated Sprint 32 query expansion and doctrinal bridge benchmark.')]
final class EvaluateDoctrinalBridgeCommand extends Command
{
    public function handle(DoctrinalBridgeExperimentService $benchmark): int
    {
        try {
            $result = $benchmark->run(
                mode: (string) $this->option('mode'),
                limit: $this->option('limit') === null ? null : max(1, (int) $this->option('limit')),
                category: $this->option('category') === null ? null : (string) $this->option('category'),
                questionId: $this->option('question-id') === null ? null : (string) $this->option('question-id'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->display($result);
        }

        if ((bool) $this->option('write-report')) {
            File::put(base_path('docs/retrieval-sprint-32-report.md'), $this->report($result));
            $this->info('Report written: docs/retrieval-sprint-32-report.md');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function display(array $result): void
    {
        $this->info('Sprint 32 Doctrinal Bridge Experiment');
        $this->line('Decision: '.$result['decision']);
        $this->line('Version: '.$result['experiment']['version']);
        $this->line('Questions: '.$result['dataset']['evaluated_questions'].' / '.$result['dataset']['defined_questions']);
        $this->line('Corpus: '.$result['production_state']['documents'].' documents, '.$result['production_state']['embedded_documents'].' embedded');
        $this->newLine();
        $this->metricsTable($result['modes']);
    }

    /**
     * @param  array<string, mixed>  $modes
     */
    private function metricsTable(array $modes): void
    {
        $rows = [];

        foreach ($modes as $mode => $result) {
            foreach (['k5', 'k10'] as $k) {
                if (! isset($result[$k])) {
                    continue;
                }

                $metrics = $result[$k];
                $rows[] = [
                    $mode,
                    strtoupper($k),
                    number_format((float) $metrics['hit_rate'] * 100, 1).'%',
                    number_format((float) $metrics['precision'], 3),
                    number_format((float) $metrics['recall'], 3),
                    number_format((float) $metrics['mrr'], 3),
                    number_format((float) $metrics['ndcg'], 3),
                    number_format((float) $metrics['source_coverage'] * 100, 1).'%',
                    $metrics['latency_ms'].' ms',
                ];
            }
        }

        $this->table(['Mode', 'K', 'Hit', 'Precision', 'Recall', 'MRR', 'NDCG', 'Source Coverage', 'Latency'], $rows);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): string
    {
        return implode("\n", [
            '# Sprint 32 - Query Expansion and Doctrinal Bridge Experiment',
            '',
            '## Decision',
            '',
            (string) $result['decision'],
            '',
            '## Scope',
            '',
            'This is an isolated retrieval experiment. It does not modify production retrieval algorithms, ranking, embeddings, corpus records, graph behavior, agents, MCP, security, or Core API contracts.',
            '',
            '## Reproducibility',
            '',
            '- Experiment version: `'.$result['experiment']['version'].'`',
            '- Config fingerprint: `'.$result['experiment']['config_fingerprint'].'`',
            '- Dataset version: `'.$result['experiment']['dataset_version'].'`',
            '- Dataset fingerprint: `'.$result['experiment']['dataset_fingerprint'].'`',
            '- Corpus fingerprint: `'.$result['experiment']['corpus_fingerprint'].'`',
            '- Timestamp: `'.$result['experiment']['timestamp'].'`',
            '',
            '## Dataset',
            '',
            '- Defined questions: `'.$result['dataset']['defined_questions'].'`',
            '- Evaluated questions: `'.$result['dataset']['evaluated_questions'].'`',
            '',
            '## Metrics',
            '',
            $this->markdownMetrics($result['modes']),
            '',
            '## John 1:1',
            '',
            'John 1:1 diagnostics are included in JSON output. This report stores summary metrics only to keep the committed artifact readable.',
            '',
            '## Production State',
            '',
            '- Documents: `'.$result['production_state']['documents'].'`',
            '- Embedded documents: `'.$result['production_state']['embedded_documents'].'`',
            '',
            '## Commands',
            '',
            '```bash',
            'php artisan retrieval:doctrinal-expand --query="Why does John teach that the Word is God?" --mode=combined --format=json',
            'php artisan evaluate:doctrinal-bridge --format=json',
            'php artisan evaluate:doctrinal-bridge --write-report',
            '```',
            '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $modes
     */
    private function markdownMetrics(array $modes): string
    {
        $lines = [
            '| Mode | K | Questions | Hit Rate | Precision | Recall | MRR | NDCG | Source Coverage | Latency |',
            '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |',
        ];

        foreach ($modes as $mode => $result) {
            foreach (['k5', 'k10'] as $k) {
                if (! isset($result[$k])) {
                    continue;
                }

                $metrics = $result[$k];
                $lines[] = '| '.$mode.' | '.$k.' | '
                    .$metrics['questions'].' | '
                    .number_format((float) $metrics['hit_rate'], 3).' | '
                    .number_format((float) $metrics['precision'], 3).' | '
                    .number_format((float) $metrics['recall'], 3).' | '
                    .number_format((float) $metrics['mrr'], 3).' | '
                    .number_format((float) $metrics['ndcg'], 3).' | '
                    .number_format((float) $metrics['source_coverage'], 3).' | '
                    .$metrics['latency_ms'].' ms |';
            }
        }

        return implode("\n", $lines);
    }
}
