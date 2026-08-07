<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Database\Factories\EvaluationQuestionRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $question
 * @property list<string> $expected_references
 * @property list<string>|null $intended_references
 * @property list<string>|null $missing_references
 * @property list<string> $expected_source_types
 * @property string $coverage_status
 * @property string|null $notes
 * @property string|null $category
 */
final class EvaluationQuestionRecord extends Model
{
    /** @use HasFactory<EvaluationQuestionRecordFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'evaluation_questions';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'question',
        'expected_references',
        'intended_references',
        'missing_references',
        'expected_source_types',
        'coverage_status',
        'notes',
        'category',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_references' => 'array',
            'intended_references' => 'array',
            'missing_references' => 'array',
            'expected_source_types' => 'array',
        ];
    }

    /** @return Factory<EvaluationQuestionRecord> */
    protected static function newFactory(): Factory
    {
        return EvaluationQuestionRecordFactory::new();
    }
}
