<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Database\Factories\RetrievalEvaluationRunRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $evaluation_question_id
 * @property string $query
 * @property int $top_k
 * @property float|null $minimum_score
 * @property string $retrieval_strategy
 * @property list<array<string, mixed>> $retrieved_results
 * @property list<string> $expected_references
 * @property bool $hit
 * @property float $precision
 * @property float $recall
 * @property float $reciprocal_rank
 * @property float $ndcg
 * @property array<string, mixed>|null $source_coverage
 * @property int $execution_time_ms
 */
final class RetrievalEvaluationRunRecord extends Model
{
    /** @use HasFactory<RetrievalEvaluationRunRecordFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'retrieval_evaluation_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'evaluation_question_id',
        'query',
        'top_k',
        'minimum_score',
        'retrieval_strategy',
        'retrieved_results',
        'expected_references',
        'hit',
        'precision',
        'recall',
        'reciprocal_rank',
        'ndcg',
        'source_coverage',
        'execution_time_ms',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'top_k' => 'integer',
            'minimum_score' => 'float',
            'retrieval_strategy' => 'string',
            'retrieved_results' => 'array',
            'expected_references' => 'array',
            'hit' => 'boolean',
            'precision' => 'float',
            'recall' => 'float',
            'reciprocal_rank' => 'float',
            'ndcg' => 'float',
            'source_coverage' => 'array',
            'execution_time_ms' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EvaluationQuestionRecord::class, 'evaluation_question_id');
    }

    /** @return Factory<RetrievalEvaluationRunRecord> */
    protected static function newFactory(): Factory
    {
        return RetrievalEvaluationRunRecordFactory::new();
    }
}
