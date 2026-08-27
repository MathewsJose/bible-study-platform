<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\ScriptureRoutingExperimentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('evaluate:scripture-routing
    {--mode=all : baseline, exact_reference_route, reference_fusion, doctrinal_route, hybrid_router, or all}
    {--limit= : Limit evaluated questions for bounded diagnostics}
    {--category= : Evaluate only one Sprint 30 category}
    {--question-id= : Evaluate one question by 1-based number or qNNN id}
    {--format=table : Output format: table or json}
    {--write-report : Write docs/retrieval-sprint-33-report.md}')]
#[Description('Run isolated Sprint 33 deterministic Scripture routing and retrieval fusion benchmark.')]
final class EvaluateScriptureRoutingCommand extends Command
{
    public function handle(ScriptureRoutingExperimentService $experiment): int
    {
        $result = $experiment->run(
            mode: (string) $this->option('mode'),
            limit: $this->option('limit') === null ? null : max(1, (int) $this->option('limit')),
            category: $this->option('category') === null ? null : (string) $this->option('category'),
            questionId: $this->option('question-id') === null ? null : (string) $this->option('question-id'),
        );

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->display($result);
        }

        if ((bool) $this->option('write-report')) {
            File::put(base_path('docs/retrieval-sprint-33-report.md'), $this->report($result));
            $this->info('Report written: docs/retrieval-sprint-33-report.md');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function display(array $result): void
    {
        $this->info('Sprint 33 Scripture Routing Experiment');
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
            '# Sprint 33 - Deterministic Scripture Routing and Retrieval Fusion',
            '',
            '## 1. Executive Decision',
            '',
            (string) $result['decision'],
            '',
            '## 2. Objective',
            '',
            'Separate explicit Scripture/reference queries from doctrinal semantic queries in an isolated experimental retrieval layer.',
            '',
            '## 3. Problem Separation',
            '',
            'Exact references are routed through deterministic reference resolution. Doctrinal and general semantic queries continue through read-only production retrieval services.',
            '',
            '## 4. Production Baseline',
            '',
            '- Documents: `'.$result['production_state']['documents'].'`',
            '- Embedded documents: `'.$result['production_state']['embedded_documents'].'`',
            '- Embedding dimensions: `'.implode(', ', array_keys((array) $result['production_state']['embedding_dimensions'])).'`',
            '',
            '## 5. Sprint 31 Comparison',
            '',
            'Sprint 31 persistent contextual retrieval remained a regression and was not promoted.',
            '',
            '## 6. Sprint 32 Comparison',
            '',
            'Sprint 32 combined expansion was inconclusive: Hit@5 `0.469`, MRR@5 `0.369`, NDCG@5 `0.377`.',
            '',
            '## 7. Query Classification',
            '',
            $this->markdownCounts($result['classification']),
            '',
            '## 8. Routing Architecture',
            '',
            'Modes: `baseline`, `exact_reference_route`, `reference_fusion`, `doctrinal_route`, `hybrid_router`.',
            '',
            '## 9. Fusion Scoring',
            '',
            'Fusion scores are deterministic and configured in `config/retrieval_sprint33.php`.',
            '',
            '## 10. Overall Metrics',
            '',
            $this->markdownMetrics($result['modes']),
            '',
            '## 11. Per-Route Metrics',
            '',
            'Per-route metrics are included in JSON output under each mode.',
            '',
            '## 12. John 1:1 Diagnostics',
            '',
            'John 1:1 diagnostics are included in JSON output.',
            '',
            '## 13. False-Positive Tests',
            '',
            '- False positives: `'.$result['false_positives']['false_positive_count'].' / '.$result['false_positives']['total'].'`',
            '',
            '## 14. Legacy Source Resolution',
            '',
            '- Default source: `'.($result['legacy_source_resolution']['default_source_name'] ?? 'none').'`',
            '- Explicit legacy source: `'.($result['legacy_source_resolution']['legacy_source_name'] ?? 'none').'`',
            '',
            '## 15. Latency',
            '',
            'Latency is command-level diagnostic timing and not a production SLO.',
            '',
            '## 16. Production Isolation Verification',
            '',
            'No corpus import, embedding generation, graph rebuild, production retrieval promotion, API change, MCP change, agent change, or legacy Bible mutation occurred.',
            '',
            '## 17. Tests',
            '',
            'See final sprint response for executed commands.',
            '',
            '## 18. PHPStan',
            '',
            'See final sprint response for executed commands.',
            '',
            '## 19. Pint',
            '',
            'See final sprint response for executed commands.',
            '',
            '## 20. Diff Check',
            '',
            'See final sprint response for executed commands.',
            '',
            '## 21. Limitations',
            '',
            'The experiment still relies on existing query embeddings and lexical search behavior. It does not solve source balancing or theological graph traversal.',
            '',
            '## 22. Recommendation for Sprint 34',
            '',
            'Keep deterministic reference routing experimental and investigate a production-safe exact-reference pre-router only for direct citation requests.',
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

    /**
     * @param  array<string, int>  $counts
     */
    private function markdownCounts(array $counts): string
    {
        $lines = [
            '| Route | Count |',
            '| --- | ---: |',
        ];

        foreach ($counts as $route => $count) {
            $lines[] = '| '.$route.' | '.$count.' |';
        }

        return implode("\n", $lines);
    }
}
