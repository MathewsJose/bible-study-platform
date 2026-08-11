<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Database\Factories\RetrievalEvaluationSummaryRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $total_questions
 * @property float $hit_rate
 * @property float $mean_precision
 * @property float $mean_recall
 * @property float $mrr
 * @property float $mean_ndcg
 * @property float $mean_source_coverage
 * @property int $average_latency_ms
 * @property array<string, mixed> $configuration
 */
final class RetrievalEvaluationSummaryRecord extends Model
{
    /** @use HasFactory<RetrievalEvaluationSummaryRecordFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'retrieval_evaluation_summaries';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'total_questions',
        'hit_rate',
        'mean_precision',
        'mean_recall',
        'mrr',
        'mean_ndcg',
        'mean_source_coverage',
        'average_latency_ms',
        'configuration',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_questions' => 'integer',
            'hit_rate' => 'float',
            'mean_precision' => 'float',
            'mean_recall' => 'float',
            'mrr' => 'float',
            'mean_ndcg' => 'float',
            'mean_source_coverage' => 'float',
            'average_latency_ms' => 'integer',
            'configuration' => 'array',
        ];
    }

    /** @return Factory<RetrievalEvaluationSummaryRecord> */
    protected static function newFactory(): Factory
    {
        return RetrievalEvaluationSummaryRecordFactory::new();
    }
}
