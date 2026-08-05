<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class RetrievalEvaluationSummary
{
    /**
     * @param  list<RetrievalEvaluationResult>  $results
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public int $totalQuestions,
        public float $hitRate,
        public float $meanPrecision,
        public float $meanRecall,
        public float $mrr,
        public int $averageLatencyMs,
        public array $configuration,
        public array $results,
        public ?string $summaryId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(bool $includeResults = false): array
    {
        $data = [
            'total_questions' => $this->totalQuestions,
            'hit_rate' => $this->hitRate,
            'precision' => $this->meanPrecision,
            'recall' => $this->meanRecall,
            'mrr' => $this->mrr,
            'average_latency_ms' => $this->averageLatencyMs,
            'configuration' => $this->configuration,
        ];

        if ($this->summaryId !== null) {
            $data['summary_id'] = $this->summaryId;
        }

        if ($includeResults) {
            $data['results'] = array_map(
                static fn (RetrievalEvaluationResult $result): array => $result->toArray(),
                $this->results,
            );
        }

        return $data;
    }
}
