<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ai_evaluation_run_id
 * @property string|null $evaluation_question_id
 * @property string $evaluation_type
 * @property string|null $category
 * @property string|null $difficulty
 * @property string $status
 * @property float $score
 * @property array<string, mixed>|null $metrics
 * @property array<string, mixed>|null $expected
 * @property array<string, mixed>|null $actual
 * @property list<string>|null $warnings
 * @property int $latency_ms
 */
final class AiEvaluationResultRecord extends Model
{
    use HasUuids;

    protected $table = 'ai_evaluation_results';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'ai_evaluation_run_id',
        'evaluation_question_id',
        'evaluation_type',
        'category',
        'difficulty',
        'status',
        'score',
        'metrics',
        'expected',
        'actual',
        'warnings',
        'latency_ms',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'metrics' => 'array',
            'expected' => 'array',
            'actual' => 'array',
            'warnings' => 'array',
            'latency_ms' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiEvaluationRunRecord::class, 'ai_evaluation_run_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EvaluationQuestionRecord::class, 'evaluation_question_id');
    }
}
