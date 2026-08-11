<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\Services;

use App\Application\Knowledge\Evaluation\DTOs\RegressionComparison;
use App\Infrastructure\Knowledge\Persistence\AiEvaluationRunRecord;
use RuntimeException;

final readonly class RegressionComparisonService
{
    public function compare(string $baselineId, string $currentId): RegressionComparison
    {
        $baseline = AiEvaluationRunRecord::query()->with('results')->find($baselineId);
        $current = AiEvaluationRunRecord::query()->with('results')->find($currentId);

        if (! $baseline instanceof AiEvaluationRunRecord || ! $current instanceof AiEvaluationRunRecord) {
            throw new RuntimeException('Evaluation run not found.');
        }

        $metricDeltas = $this->metricDeltas((array) $baseline->metrics, (array) $current->metrics);
        [$improved, $regressed, $unchanged] = $this->questionDeltas($baseline, $current);
        $failures = $this->thresholdFailures($metricDeltas, (array) $current->metrics);

        return new RegressionComparison(
            baselineId: $baselineId,
            currentId: $currentId,
            status: $failures === [] ? 'PASS' : 'FAIL',
            metricDeltas: $metricDeltas,
            improvedQuestions: $improved,
            regressedQuestions: $regressed,
            unchangedQuestions: $unchanged,
            failures: $failures,
        );
    }

    /**
     * @param  array<string, mixed>  $baseline
     * @param  array<string, mixed>  $current
     * @return array<string, array{baseline: float|int, current: float|int, delta: float|int}>
     */
    private function metricDeltas(array $baseline, array $current): array
    {
        $keys = array_values(array_unique([...array_keys($baseline), ...array_keys($current)]));
        $deltas = [];

        foreach ($keys as $key) {
            if (! is_numeric($baseline[$key] ?? null) || ! is_numeric($current[$key] ?? null)) {
                continue;
            }

            $base = (float) $baseline[$key];
            $now = (float) $current[$key];
            $deltas[(string) $key] = [
                'baseline' => $base,
                'current' => $now,
                'delta' => round($now - $base, 6),
            ];
        }

        return $deltas;
    }

    /** @return array{0: list<string>, 1: list<string>, 2: list<string>} */
    private function questionDeltas(AiEvaluationRunRecord $baseline, AiEvaluationRunRecord $current): array
    {
        $baselineResults = $baseline->results->keyBy(fn ($result): string => (string) ($result->evaluation_question_id ?: $result->category));
        $improved = [];
        $regressed = [];
        $unchanged = [];

        foreach ($current->results as $result) {
            $key = (string) ($result->evaluation_question_id ?: $result->category);
            $previous = $baselineResults->get($key);

            if ($previous === null) {
                continue;
            }

            $label = $key;
            if ($result->score > $previous->score) {
                $improved[] = $label;
            } elseif ($result->score < $previous->score) {
                $regressed[] = $label;
            } else {
                $unchanged[] = $label;
            }
        }

        return [$improved, $regressed, $unchanged];
    }

    /**
     * @param  array<string, mixed>  $metricDeltas
     * @param  array<string, mixed>  $current
     * @return list<string>
     */
    private function thresholdFailures(array $metricDeltas, array $current): array
    {
        $failures = [];
        $thresholds = (array) config('evaluation.thresholds', []);

        if (($current['average_score'] ?? 1.0) < (float) ($thresholds['minimum_average_score'] ?? 0.0)) {
            $failures[] = 'average score below threshold';
        }

        if (($current['average_latency_ms'] ?? 0) > (int) ($thresholds['maximum_latency_ms'] ?? PHP_INT_MAX)) {
            $failures[] = 'latency above threshold';
        }

        $total = max(1, (int) ($current['total'] ?? 1));
        $failureRate = ((int) ($current['failed'] ?? 0)) / $total;
        if ($failureRate > (float) ($thresholds['maximum_failure_rate'] ?? 1.0)) {
            $failures[] = 'failure rate above threshold';
        }

        $scoreDelta = (float) ($metricDeltas['average_score']['delta'] ?? 0.0);
        if ($scoreDelta < -1 * (float) ($thresholds['maximum_score_drop'] ?? 1.0)) {
            $failures[] = 'average score regression exceeds threshold';
        }

        return $failures;
    }
}
