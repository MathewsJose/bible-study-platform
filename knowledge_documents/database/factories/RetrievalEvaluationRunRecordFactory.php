<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\RetrievalEvaluationRunRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetrievalEvaluationRunRecord>
 */
final class RetrievalEvaluationRunRecordFactory extends Factory
{
    protected $model = RetrievalEvaluationRunRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'evaluation_question_id' => EvaluationQuestionRecord::factory(),
            'query' => 'Why did Jesus become man?',
            'top_k' => 5,
            'minimum_score' => 0.7,
            'retrieved_results' => [],
            'expected_references' => ['CCC 457'],
            'hit' => false,
            'precision' => 0.0,
            'recall' => 0.0,
            'reciprocal_rank' => 0.0,
            'execution_time_ms' => 10,
        ];
    }
}
