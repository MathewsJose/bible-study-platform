<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationQuestionRecord>
 */
final class EvaluationQuestionRecordFactory extends Factory
{
    protected $model = EvaluationQuestionRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'question' => 'Why did Jesus become man?',
            'expected_references' => ['CCC 457'],
            'expected_source_types' => [SourceType::Catechism->value],
            'notes' => null,
            'category' => 'christology',
        ];
    }
}
