<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Replay\Services\ExecutionFingerprintService;
use App\Application\Knowledge\Retrieval\Experiments\ContextualRetrievalExperimentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('evaluate:contextual-retrieval
    {--format=table : Output format: table or json}
    {--write-report : Write docs/retrieval-sprint-30-report.md}
    {--candidate-limit= : Candidate pool size for isolated contextual reranking}
    {--limit= : Limit evaluated questions for bounded local diagnostics}
    {--context-modes=verse_only : Comma-separated contextual modes to run, or none}')]
#[Description('Run isolated Sprint 30 contextual retrieval experiments without changing production retrieval.')]
final class EvaluateContextualRetrievalCommand extends Command
{
    public function handle(ContextualRetrievalExperimentService $experiments, ExecutionFingerprintService $fingerprints): int
    {
        if ($this->option('candidate-limit') !== null) {
            config()->set('retrieval_sprint30.candidate_limit', max(1, (int) $this->option('candidate-limit')));
        }

        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));
        $contextModes = $this->contextModes();
        $result = $experiments->run($limit, $contextModes);
        $fingerprint = $fingerprints->forProfile('catholic_research');
        $result['fingerprints'] = [
            'execution_hash' => $fingerprint['hash'],
            'corpus_hash' => $fingerprint['corpus']['hash'] ?? null,
            'document_count' => $fingerprint['corpus']['document_count'] ?? null,
            'embedding_models' => $fingerprint['corpus']['embedding_models'] ?? [],
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->display($result);
        }

        if ((bool) $this->option('write-report')) {
            File::put(base_path('docs/retrieval-sprint-30-report.md'), $this->report($result));
            $this->info('Report written: docs/retrieval-sprint-30-report.md');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function contextModes(): array
    {
        $value = (string) $this->option('context-modes');

        if ($value === 'none') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $mode): string => trim($mode), explode(',', $value)),
            static fn (string $mode): bool => $mode !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function display(array $result): void
    {
        $this->info('Sprint 30 Contextual Retrieval Experiments');
        $this->line('Dataset: '.$result['dataset']['version']);
        $this->line('Defined questions: '.$result['dataset']['defined_questions']);
        $this->line('Evaluated questions: '.$result['dataset']['evaluated_questions']);
        $this->line('Valid: '.$result['dataset']['validation']['valid']);

        $this->newLine();
        $this->info('Production Baseline');
        $this->metricsTable($result['production']);

        $this->newLine();
        $this->info('Contextual Experiments');
        $this->metricsTable($result['contextual']);

        $this->newLine();
        $this->info('Document Type Weighting');
        $this->metricsTable($result['document_type_weighting']);

        $this->newLine();
        $this->info('Exact Reference Boosting');
        $this->metricsTable(['exact_reference_boosting' => $result['exact_reference_boosting']]);
    }

    /**
     * @param  array<string, mixed>  $groups
     */
    private function metricsTable(array $groups): void
    {
        $rows = [];

        foreach ($groups as $name => $metrics) {
            foreach (['k5', 'k10'] as $k) {
                if (! isset($metrics[$k])) {
                    continue;
                }

                $rows[] = [
                    $name,
                    strtoupper($k),
                    number_format((float) $metrics[$k]['hit_rate'] * 100, 1).'%',
                    number_format((float) $metrics[$k]['recall'], 3),
                    number_format((float) $metrics[$k]['mrr'], 3),
                    number_format((float) $metrics[$k]['ndcg'], 3),
                    number_format((float) $metrics[$k]['source_coverage'] * 100, 1).'%',
                    $metrics[$k]['latency_ms'].' ms',
                ];
            }
        }

        $this->table(['Experiment', 'K', 'Hit', 'Recall', 'MRR', 'NDCG', 'Source Coverage', 'Latency'], $rows);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function report(array $result): string
    {
        $lines = [
            '# Sprint 30 - Contextual Retrieval Architecture & Evaluation Expansion',
            '',
            '## 1. Executive Decision',
            '',
            'REGRESSION - production retrieval remains below the small-corpus baseline. Isolated contextual reranking improves selected exact/contextual cases but is not yet strong enough to promote as production behavior.',
            '',
            '## 2. Sprint 28 Baseline',
            '',
            $this->markdownTable($result['production']),
            '',
            '## 3. Expanded Evaluation Dataset Description',
            '',
            'The Sprint 30 dataset is retrieval-only, versioned as `'.$result['dataset']['version'].'`, and stored in `config/retrieval_sprint30.php`. It does not replace or mutate the existing six stored evaluation questions.',
            '',
            '## 4. Evaluation Questions/Count',
            '',
            '- Defined questions: '.$result['dataset']['defined_questions'],
            '- Evaluated questions in this run: '.$result['dataset']['evaluated_questions'],
            '- Valid questions: '.$result['dataset']['validation']['valid'],
            '- Categories: `'.implode('`, `', array_keys($result['dataset']['categories'])).'`',
            '',
            '## 5. Contextualization Design',
            '',
            'Contextual experiments build in-memory semantic units that preserve the exact target document ID/reference. Bible verse units can be represented as verse-only text, labeled verse text, adjacent verse context, plus/minus three verses, or target verse plus chapter context. No production document content or stored embedding is changed.',
            '',
            '## 6. Experiment A Results',
            '',
            $this->markdownTable(['experiment_a_verse_only' => $result['contextual']['experiment_a_verse_only']]),
            '',
            '## 7. Experiment B Results',
            '',
            $this->markdownTable(['experiment_b_adjacent' => $result['contextual']['experiment_b_adjacent']]),
            '',
            '## 8. Experiment C Results',
            '',
            $this->markdownTable(['experiment_c_window_3' => $result['contextual']['experiment_c_window_3']]),
            '',
            '## 9. Experiment D Results',
            '',
            $this->markdownTable(['experiment_d_labeled_verse' => $result['contextual']['experiment_d_labeled_verse']]),
            '',
            '## 10. Experiment E Results',
            '',
            $this->markdownTable(['experiment_e_chapter_context' => $result['contextual']['experiment_e_chapter_context']]),
            '',
            '## 11. Document-Type Weighting Results',
            '',
            $this->markdownTable($result['document_type_weighting']),
            '',
            '## 12. Exact-Reference Boosting Results',
            '',
            $this->markdownTable(['exact_reference_boosting' => $result['exact_reference_boosting']]),
            '',
            '## 13. John 1:1 Diagnostic',
            '',
            'Exact-reference queries recover `John 1:1` reliably when deterministic boosting is available. Pure theological phrasing without explicit reference still depends on semantic context and remains the key risk.',
            '',
            '## 14. Source Coverage',
            '',
            'Source coverage is reported per experiment in the tables above. Multi-source coverage remains constrained by the small non-Bible corpus: 7 Catechism documents and 3 Church Father documents.',
            '',
            '## 15. Latency',
            '',
            'Latencies are total command-time measurements for each experiment group and include local embedding calls for contextual reranking. They are suitable for comparison, not production SLOs.',
            '',
            '## 16. Memory/Resource Observations',
            '',
            'The contextual experiment reranks a bounded candidate pool from production vector/hybrid retrieval plus expected references. It avoids building a full 37k in-memory contextual index.',
            '',
            '## 17. Root-Cause Conclusion',
            '',
            'The regression is primarily caused by sparse verse-level semantic representations and evaluation questions that expect theological references whose literal text lacks the query terms. Chapter documents also compete strongly in lexical retrieval.',
            '',
            '## 18. Recommended Production Architecture',
            '',
            'Add a separate contextual retrieval index/table in a future sprint, preserving target `knowledge_document_id`, exact reference, source metadata, context window, embedding model, and reproducible checksum. Keep exact-reference boosting deterministic and scoped only to explicit references.',
            '',
            '## 19. Recommended Sprint 31',
            '',
            'Build a persistent experimental contextual index behind a disabled-by-default profile, generate contextual embeddings for Bible verses only, and compare against this Sprint 30 dataset before promoting any profile.',
            '',
            '## 20. Risks',
            '',
            '- Candidate-pool reranking can overestimate full-corpus contextual performance.',
            '- Querying all contextual variants would require storage/index design and embedding generation.',
            '- Exact-reference boosting must remain scoped to explicit references.',
            '',
            '## 21. Reproducibility',
            '',
            '- Corpus hash: `'.($result['fingerprints']['corpus_hash'] ?? 'unknown').'`',
            '- Execution hash: `'.($result['fingerprints']['execution_hash'] ?? 'unknown').'`',
            '- Document count: `'.($result['fingerprints']['document_count'] ?? 'unknown').'`',
            '- Embedding models: `'.implode('`, `', $result['fingerprints']['embedding_models'] ?? []).'`',
            '',
            '## 22. Exact Commands',
            '',
            '```bash',
            'php artisan evaluate:contextual-retrieval --write-report',
            'php artisan evaluate:contextual-retrieval --format=json',
            'php artisan test --compact tests/Unit/ContextualRetrievalExperimentTest.php',
            'vendor/bin/phpstan analyse --memory-limit=1G',
            'vendor/bin/pint --dirty --format agent',
            'git diff --check',
            '```',
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $groups
     */
    private function markdownTable(array $groups): string
    {
        $lines = [
            '| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |',
            '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |',
        ];

        foreach ($groups as $name => $metrics) {
            foreach (['k5', 'k10'] as $k) {
                if (! isset($metrics[$k])) {
                    continue;
                }

                $lines[] = '| '.$name.' | '.strtoupper($k).' | '
                    .number_format((float) $metrics[$k]['hit_rate'], 3).' | '
                    .number_format((float) $metrics[$k]['precision'], 3).' | '
                    .number_format((float) $metrics[$k]['recall'], 3).' | '
                    .number_format((float) $metrics[$k]['mrr'], 3).' | '
                    .number_format((float) $metrics[$k]['ndcg'], 3).' | '
                    .number_format((float) $metrics[$k]['source_coverage'], 3).' | '
                    .$metrics[$k]['latency_ms'].' ms |';
            }
        }

        return implode("\n", $lines);
    }
}
