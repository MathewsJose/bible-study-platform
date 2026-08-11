<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Evaluation\Services\RegressionComparisonService;
use Illuminate\Console\Command;
use RuntimeException;

final class AiEvaluateCompareCommand extends Command
{
    protected $signature = 'ai:evaluate:compare
                            {--baseline= : Baseline ai_evaluation_runs id}
                            {--current= : Current ai_evaluation_runs id}
                            {--format=table : table or json}';

    protected $description = 'Compare two saved AI evaluation runs and report regressions.';

    public function handle(RegressionComparisonService $comparisons): int
    {
        $baseline = (string) $this->option('baseline');
        $current = (string) $this->option('current');

        if ($baseline === '' || $current === '') {
            $this->error('Both --baseline and --current are required.');

            return self::FAILURE;
        }

        try {
            $comparison = $comparisons->compare($baseline, $current);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($comparison->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $comparison->status === 'PASS' ? self::SUCCESS : self::FAILURE;
        }

        $this->info('AI Evaluation Regression Comparison');
        $this->table(
            ['Metric', 'Baseline', 'Current', 'Delta'],
            array_map(static fn (string $metric, array $delta): array => [
                $metric,
                (string) $delta['baseline'],
                (string) $delta['current'],
                ((float) $delta['delta'] >= 0 ? '+' : '').(string) $delta['delta'],
            ], array_keys($comparison->metricDeltas), array_values($comparison->metricDeltas)),
        );
        $this->line('Improved questions: '.count($comparison->improvedQuestions));
        $this->line('Regressed questions: '.count($comparison->regressedQuestions));
        $this->line('Unchanged questions: '.count($comparison->unchangedQuestions));
        $this->line('Regression status: '.$comparison->status);

        foreach ($comparison->failures as $failure) {
            $this->warn($failure);
        }

        return $comparison->status === 'PASS' ? self::SUCCESS : self::FAILURE;
    }
}
