<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $evaluation_type
 * @property string $status
 * @property int $total_questions
 * @property array<string, mixed>|null $metrics
 * @property array<string, mixed>|null $configuration
 * @property array<string, mixed>|null $fingerprints
 * @property array<string, mixed>|null $thresholds
 * @property array<string, mixed>|null $metadata
 */
final class AiEvaluationRunRecord extends Model
{
    use HasUuids;

    protected $table = 'ai_evaluation_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'evaluation_type',
        'status',
        'started_at',
        'completed_at',
        'total_questions',
        'metrics',
        'configuration',
        'fingerprints',
        'thresholds',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'total_questions' => 'integer',
            'metrics' => 'array',
            'configuration' => 'array',
            'fingerprints' => 'array',
            'thresholds' => 'array',
            'metadata' => 'array',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(AiEvaluationResultRecord::class, 'ai_evaluation_run_id');
    }
}
