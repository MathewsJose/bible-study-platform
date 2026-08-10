<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Agents\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $request_id
 * @property string $profile
 * @property string $status
 * @property string|null $failure_category
 * @property \Carbon\CarbonImmutable|null $started_at
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property int $duration_ms
 * @property int $step_count
 * @property int $tool_call_count
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $total_tokens
 * @property array<string, mixed>|null $input_metadata
 * @property array<string, mixed>|null $retrieval_metrics
 * @property array<string, mixed>|null $answer_metrics
 * @property array<string, mixed>|null $error_information
 * @property array<string, mixed>|null $metadata
 */
final class AgentExecutionRecord extends Model
{
    use HasUuids;

    protected $table = 'agent_executions';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'request_id',
        'profile',
        'status',
        'failure_category',
        'started_at',
        'completed_at',
        'duration_ms',
        'step_count',
        'tool_call_count',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'input_metadata',
        'retrieval_metrics',
        'answer_metrics',
        'error_information',
        'metadata',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'running',
        'duration_ms' => 0,
        'step_count' => 0,
        'tool_call_count' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'input_metadata' => 'array',
            'retrieval_metrics' => 'array',
            'answer_metrics' => 'array',
            'error_information' => 'array',
            'metadata' => 'array',
            'duration_ms' => 'integer',
            'step_count' => 'integer',
            'tool_call_count' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
        ];
    }

    /** @return HasMany<AgentExecutionStepRecord, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(AgentExecutionStepRecord::class, 'agent_execution_id')->orderBy('step_number');
    }
}
