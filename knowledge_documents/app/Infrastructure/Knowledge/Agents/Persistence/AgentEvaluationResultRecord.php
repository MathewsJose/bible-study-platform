<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Agents\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AgentEvaluationResultRecord extends Model
{
    use HasUuids;

    protected $table = 'agent_evaluation_results';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'agent_evaluation_run_id',
        'scenario_name',
        'status',
        'step_count',
        'latency_ms',
        'expected_tools',
        'actual_tools',
        'missing_tools',
        'extra_tools',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'step_count' => 'integer',
            'latency_ms' => 'integer',
            'expected_tools' => 'array',
            'actual_tools' => 'array',
            'missing_tools' => 'array',
            'extra_tools' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AgentEvaluationRunRecord, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentEvaluationRunRecord::class, 'agent_evaluation_run_id');
    }
}
