<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\DTOs;

final readonly class RegressionComparison
{
    /**
     * @param  array<string, mixed>  $metricDeltas
     * @param  list<string>  $improvedQuestions
     * @param  list<string>  $regressedQuestions
     * @param  list<string>  $unchangedQuestions
     * @param  list<string>  $failures
     */
    public function __construct(
        public string $baselineId,
        public string $currentId,
        public string $status,
        public array $metricDeltas,
        public array $improvedQuestions,
        public array $regressedQuestions,
        public array $unchangedQuestions,
        public array $failures,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'baseline_id' => $this->baselineId,
            'current_id' => $this->currentId,
            'status' => $this->status,
            'metric_deltas' => $this->metricDeltas,
            'improved_questions' => $this->improvedQuestions,
            'regressed_questions' => $this->regressedQuestions,
            'unchanged_questions' => $this->unchangedQuestions,
            'failures' => $this->failures,
        ];
    }
}
