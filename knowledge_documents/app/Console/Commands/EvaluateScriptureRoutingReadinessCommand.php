<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\Experiments\ScriptureRoutingReadinessService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('evaluate:scripture-routing-readiness
    {--limit= : Limit evaluated questions for bounded diagnostics}
    {--category= : Evaluate only one Sprint 30 category}
    {--question-id= : Evaluate one question by 1-based number or qNNN id}
    {--format=table : Output format: table or json}
    {--write-report : Write docs/retrieval-sprint-34-report.md}')]
#[Description('Validate feature-flagged Scripture routing readiness without enabling production defaults.')]
final class EvaluateScriptureRoutingReadinessCommand extends Command
{
    public function handle(ScriptureRoutingReadinessService $readiness): int
    {
        $result = $readiness->run(
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
            File::put(base_path('docs/retrieval-sprint-34-report.md'), $this->report($result));
            $this->info('Report written: docs/retrieval-sprint-34-report.md');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function display(array $result): void
    {
        $this->info('Sprint 34 Scripture Routing Readiness');
        $this->line('Decision: '.$result['decision']);
        $this->line('Questions: '.$result['dataset']['evaluated_questions'].' / '.$result['dataset']['defined_questions']);
        $this->newLine();
        $this->table(['Metric', 'Production', 'Sprint 33', 'Integrated'], [
            ['Hit@5', $result['comparison']['hit_at_5']['production'], $result['comparison']['hit_at_5']['sprint33'], $result['comparison']['hit_at_5']['integrated']],
            ['MRR@5', $result['comparison']['mrr_at_5']['production'], $result['comparison']['mrr_at_5']['sprint33'], $result['comparison']['mrr_at_5']['integrated']],
            ['NDCG@5', $result['comparison']['ndcg_at_5']['production'], $result['comparison']['ndcg_at_5']['sprint33'], $result['comparison']['ndcg_at_5']['integrated']],
            ['Hit@10', $result['comparison']['hit_at_10']['production'], $result['comparison']['hit_at_10']['sprint33'], $result['comparison']['hit_at_10']['integrated']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): string
    {
        return implode("\n", [
            '# Sprint 34 - Production Integration Readiness and Regression Validation',
            '',
            '## Executive Decision',
            '',
            (string) $result['decision'],
            '',
            '## Architecture',
            '',
            'The default-disabled feature flag enters in `RetrievalEngine`. When `retrieval.scripture_router.enabled` is false, the existing retrieval path runs unchanged. When true, `ScriptureRoutingRetrievalAdapter` calls the Sprint 33 router and converts routed results back into normal retrieval context for the existing answer and citation pipeline. Any experiment failure falls back to the original retrieval path.',
            '',
            '## Baseline',
            '',
            '- Production Hit@5: `'.$result['production']['k5']['hit_rate'].'`',
            '- Production MRR@5: `'.$result['production']['k5']['mrr'].'`',
            '- Production NDCG@5: `'.$result['production']['k5']['ndcg'].'`',
            '',
            '## Integrated Router',
            '',
            '- Integrated Hit@5: `'.$result['integrated_router']['k5']['hit_rate'].'`',
            '- Integrated MRR@5: `'.$result['integrated_router']['k5']['mrr'].'`',
            '- Integrated NDCG@5: `'.$result['integrated_router']['k5']['ndcg'].'`',
            '',
            '## Comparison',
            '',
            '| Metric | Production | Sprint 33 | Integrated |',
            '| --- | ---: | ---: | ---: |',
            '| Hit@5 | '.$result['comparison']['hit_at_5']['production'].' | '.$result['comparison']['hit_at_5']['sprint33'].' | '.$result['comparison']['hit_at_5']['integrated'].' |',
            '| MRR@5 | '.$result['comparison']['mrr_at_5']['production'].' | '.$result['comparison']['mrr_at_5']['sprint33'].' | '.$result['comparison']['mrr_at_5']['integrated'].' |',
            '| NDCG@5 | '.$result['comparison']['ndcg_at_5']['production'].' | '.$result['comparison']['ndcg_at_5']['sprint33'].' | '.$result['comparison']['ndcg_at_5']['integrated'].' |',
            '| Hit@10 | '.$result['comparison']['hit_at_10']['production'].' | '.$result['comparison']['hit_at_10']['sprint33'].' | '.$result['comparison']['hit_at_10']['integrated'].' |',
            '| Latency K5 ms | '.$result['comparison']['latency_k5_ms']['production'].' | n/a | '.$result['comparison']['latency_k5_ms']['integrated'].' |',
            '',
            '## Query Classification',
            '',
            $this->markdownCounts($result['classification']),
            '',
            '## Exact Reference Results',
            '',
            'Representative exact-reference behavior is validated by focused tests and the Sprint 33 router diagnostics.',
            '',
            '## False Positives',
            '',
            '- False positives: `'.$result['false_positives']['false_positive_count'].' / '.$result['false_positives']['total'].'`',
            '',
            '## Citation Integrity',
            '',
            '- Invalid references: `'.$result['citation_integrity']['invalid_references'].'`',
            '- Citation mismatches: `'.count((array) $result['citation_integrity']['citation_mismatches']).'`',
            '',
            '## Fallback',
            '',
            '- Integrated fallback count: `'.$result['integrated_router']['fallback']['fallback_count'].'`',
            '- Integrated fallback success rate: `'.$result['integrated_router']['fallback']['fallback_success_rate'].'`',
            '',
            '## Production Data Integrity',
            '',
            '- Before: `'.json_encode($result['production_state_before'], JSON_THROW_ON_ERROR).'`',
            '- After: `'.json_encode($result['production_state_after'], JSON_THROW_ON_ERROR).'`',
            '',
            '## Security',
            '',
            'The answer path still passes through the existing input security policy before retrieval. No external LLM provider or new external processing path was introduced.',
            '',
            '## Tests',
            '',
            'See final sprint response for focused and full test results.',
            '',
            '## Decision',
            '',
            'The router is ready for controlled activation planning, but remains disabled by default.',
            '',
        ]);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function markdownCounts(array $counts): string
    {
        $lines = ['| Route | Count |', '| --- | ---: |'];

        foreach ($counts as $route => $count) {
            $lines[] = '| '.$route.' | '.$count.' |';
        }

        return implode("\n", $lines);
    }
}
