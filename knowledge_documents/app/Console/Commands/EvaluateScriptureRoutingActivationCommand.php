<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\ScriptureRoutingActivationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('evaluate:scripture-routing-activation
    {--limit= : Limit benchmark questions for bounded diagnostics}
    {--format=table : Output format: table or json}
    {--write-report : Write docs/retrieval-sprint-35-report.md}')]
#[Description('Run controlled Scripture routing activation checks without changing production defaults.')]
final class EvaluateScriptureRoutingActivationCommand extends Command
{
    public function handle(ScriptureRoutingActivationService $activation): int
    {
        $result = $activation->run(
            limit: $this->option('limit') === null ? null : max(1, (int) $this->option('limit')),
        );

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->display($result);
        }

        if ((bool) $this->option('write-report')) {
            File::put(base_path('docs/retrieval-sprint-35-report.md'), $this->report($result));
            $this->info('Report written: docs/retrieval-sprint-35-report.md');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function display(array $result): void
    {
        $comparison = $result['readiness']['comparison'];

        $this->info('Sprint 35 Scripture Routing Activation');
        $this->line('Decision: '.$result['decision']);
        $this->line('Controlled activation: '.($result['feature_flag']['controlled_activation'] ? 'yes' : 'no'));
        $this->line('Feature flag: '.$result['feature_flag']['name'].' (default: false)');
        $this->newLine();
        $this->table(['Metric', 'Production', 'Sprint 33', 'Activated'], [
            ['Hit@5', $comparison['hit_at_5']['production'], $comparison['hit_at_5']['sprint33'], $comparison['hit_at_5']['integrated']],
            ['MRR@5', $comparison['mrr_at_5']['production'], $comparison['mrr_at_5']['sprint33'], $comparison['mrr_at_5']['integrated']],
            ['NDCG@5', $comparison['ndcg_at_5']['production'], $comparison['ndcg_at_5']['sprint33'], $comparison['ndcg_at_5']['integrated']],
            ['Hit@10', $comparison['hit_at_10']['production'], $comparison['hit_at_10']['sprint33'], $comparison['hit_at_10']['integrated']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): string
    {
        $readiness = $result['readiness'];
        $comparison = $readiness['comparison'];

        return implode("\n", [
            '# Sprint 35 - Controlled Production Activation of Deterministic Scripture Routing',
            '',
            '## 1. Executive Decision',
            '',
            (string) $result['decision'],
            '',
            '## 2. Activation Configuration',
            '',
            '- Router state: controlled diagnostic activation only',
            '- Feature flag: `'.$result['feature_flag']['name'].'`',
            '- Config key: `'.$result['feature_flag']['config_key'].'`',
            '- Default: `false`',
            '- Mode: `'.$result['feature_flag']['mode'].'`',
            '- Global default changed: `false`',
            '',
            '## 3. Rollback Procedure',
            '',
            (string) $result['rollback']['procedure'],
            '',
            '## 4. Architecture',
            '',
            'When the flag is disabled, `RetrievalEngine` uses the existing production retrieval path. When enabled, `ScriptureRoutingRetrievalAdapter` calls the Sprint 33 `hybrid_router`, converts routed candidates back into normal retrieval context, and keeps the existing answer and citation pipeline. Router failures are logged and fall back to the original retrieval path.',
            '',
            '## 5. Baseline Metrics',
            '',
            '- Hit@5: `'.$comparison['hit_at_5']['production'].'`',
            '- MRR@5: `'.$comparison['mrr_at_5']['production'].'`',
            '- NDCG@5: `'.$comparison['ndcg_at_5']['production'].'`',
            '- Hit@10: `'.$comparison['hit_at_10']['production'].'`',
            '',
            '## 6. Integrated Metrics',
            '',
            '- Hit@5: `'.$comparison['hit_at_5']['integrated'].'`',
            '- MRR@5: `'.$comparison['mrr_at_5']['integrated'].'`',
            '- NDCG@5: `'.$comparison['ndcg_at_5']['integrated'].'`',
            '- Hit@10: `'.$comparison['hit_at_10']['integrated'].'`',
            '',
            '## 7. Sprint 33 Comparison',
            '',
            '| Metric | Production | Sprint 33 | Activated |',
            '| --- | ---: | ---: | ---: |',
            '| Hit@5 | '.$comparison['hit_at_5']['production'].' | '.$comparison['hit_at_5']['sprint33'].' | '.$comparison['hit_at_5']['integrated'].' |',
            '| MRR@5 | '.$comparison['mrr_at_5']['production'].' | '.$comparison['mrr_at_5']['sprint33'].' | '.$comparison['mrr_at_5']['integrated'].' |',
            '| NDCG@5 | '.$comparison['ndcg_at_5']['production'].' | '.$comparison['ndcg_at_5']['sprint33'].' | '.$comparison['ndcg_at_5']['integrated'].' |',
            '| Hit@10 | '.$comparison['hit_at_10']['production'].' | '.$comparison['hit_at_10']['sprint33'].' | '.$comparison['hit_at_10']['integrated'].' |',
            '| Latency K5 ms | '.$comparison['latency_k5_ms']['production'].' | n/a | '.$comparison['latency_k5_ms']['integrated'].' |',
            '',
            '## 8. Category Metrics',
            '',
            $this->markdownJson($readiness['integrated_router']['per_route']),
            '',
            '## 9. Exact-Reference Results',
            '',
            $this->markdownRows($result['activation']['exact_references'], ['query', 'top_reference', 'top_source_name', 'route', 'passed']),
            '',
            '## 10. John 1:1 Results',
            '',
            $this->markdownJson($this->johnOneResult($result['activation']['exact_references'])),
            '',
            '## 11. False-Positive Results',
            '',
            '- False positives: `'.$readiness['false_positives']['false_positive_count'].' / '.$readiness['false_positives']['total'].'`',
            '',
            '## 12. Citation Integrity',
            '',
            '- Invalid references: `'.$readiness['citation_integrity']['invalid_references'].'`',
            '- Citation mismatches: `'.count((array) $readiness['citation_integrity']['citation_mismatches']).'`',
            '',
            '## 13. Fallback Results',
            '',
            $this->markdownJson($result['fallback']),
            '',
            '## 14. API Compatibility',
            '',
            'Feature tests cover the public `/api/answers` envelope with the router enabled. The command keeps diagnostics internal and does not add public API fields.',
            '',
            '## 15. Latency',
            '',
            '- Benchmark runtime ms: `'.$readiness['experiment']['runtime_ms'].'`',
            '- Activation diagnostic runtime ms: `'.$result['runtime_ms'].'`',
            '- Production K5 latency ms: `'.$comparison['latency_k5_ms']['production'].'`',
            '- Activated K5 latency ms: `'.$comparison['latency_k5_ms']['integrated'].'`',
            '',
            '## 16. Security Verification',
            '',
            $this->markdownJson($result['security']),
            '',
            '## 17. Production DB Before/After',
            '',
            '- Before: `'.json_encode($result['production_state_before'], JSON_THROW_ON_ERROR).'`',
            '- After: `'.json_encode($result['production_state_after'], JSON_THROW_ON_ERROR).'`',
            '- Duplicates: `'.json_encode($result['duplicates'], JSON_THROW_ON_ERROR).'`',
            '',
            '## 18. Tests',
            '',
            'See final sprint response for focused and full test results.',
            '',
            '## 19. PHPStan',
            '',
            'See final sprint response.',
            '',
            '## 20. Pint',
            '',
            'See final sprint response.',
            '',
            '## 21. Diff Check',
            '',
            'See final sprint response.',
            '',
            '## 22. Docker Verification',
            '',
            'See final sprint response for Docker graph, retrieval health, readiness, activation, and API-level checks.',
            '',
            '## 23. Remaining Risks',
            '',
            '- Multi-reference behavior is deterministic for explicitly detected references, but theological relationship synthesis remains intentionally out of scope.',
            '- Graph edges remain `0`, so graph-based doctrinal traversal is not part of this activation.',
            '',
            '## 24. Final Recommendation',
            '',
            'Proceed to Sprint 36 production observation and tuning with the router feature-flagged, reversible, observable, and fallback-capable.',
            '',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    private function markdownRows(array $rows, array $columns): string
    {
        $lines = [
            '| '.implode(' | ', $columns).' |',
            '| '.implode(' | ', array_fill(0, count($columns), '---')).' |',
        ];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(static fn (string $column): string => (string) ($row[$column] ?? ''), $columns)).' |';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function markdownJson(array $value): string
    {
        return "```json\n".json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n```";
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function johnOneResult(array $rows): array
    {
        foreach ($rows as $row) {
            if (($row['query'] ?? null) === 'John 1:1') {
                return $row;
            }
        }

        return [];
    }
}
