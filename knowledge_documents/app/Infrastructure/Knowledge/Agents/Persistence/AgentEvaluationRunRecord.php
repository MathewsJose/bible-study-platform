<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Agents\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AgentEvaluationRunRecord extends Model
{
    use HasUuids;

    protected $table = 'agent_evaluation_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'dataset_version',
        'profile',
        'started_at',
        'completed_at',
        'total_tasks',
        'successful_tasks',
        'failed_tasks',
        'success_rate',
        'average_steps',
        'average_latency_ms',
        'unnecessary_tool_calls',
        'regression',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'total_tasks' => 'integer',
            'successful_tasks' => 'integer',
            'failed_tasks' => 'integer',
            'success_rate' => 'float',
            'average_steps' => 'float',
            'average_latency_ms' => 'float',
            'unnecessary_tool_calls' => 'integer',
            'regression' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<AgentEvaluationResultRecord, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(AgentEvaluationResultRecord::class, 'agent_evaluation_run_id');
    }
}
