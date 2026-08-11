<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\DTOs;

final readonly class AiEvaluationResultData
{
    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $evaluationType,
        public ?string $questionId,
        public ?string $category,
        public ?string $difficulty,
        public string $status,
        public float $score,
        public array $metrics = [],
        public array $expected = [],
        public array $actual = [],
        public array $warnings = [],
        public int $latencyMs = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'evaluation_type' => $this->evaluationType,
            'question_id' => $this->questionId,
            'category' => $this->category,
            'difficulty' => $this->difficulty,
            'status' => $this->status,
            'score' => $this->score,
            'metrics' => $this->metrics,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'warnings' => $this->warnings,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
