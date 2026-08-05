<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Knowledge\Persistence\RetrievalEvaluationSummaryRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetrievalEvaluationSummaryRecord>
 */
final class RetrievalEvaluationSummaryRecordFactory extends Factory
{
    protected $model = RetrievalEvaluationSummaryRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'total_questions' => 1,
            'hit_rate' => 0.0,
            'mean_precision' => 0.0,
            'mean_recall' => 0.0,
            'mrr' => 0.0,
            'average_latency_ms' => 10,
            'configuration' => ['top_k' => 5],
        ];
    }
}
