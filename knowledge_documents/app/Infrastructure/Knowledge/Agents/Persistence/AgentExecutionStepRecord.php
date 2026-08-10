<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Agents\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $agent_execution_id
 * @property int $step_number
 * @property string $action_type
 * @property string $tool_name
 * @property string $status
 * @property string|null $failure_category
 * @property \Carbon\CarbonImmutable|null $started_at
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property int $duration_ms
 * @property array<string, mixed>|null $input_metadata
 * @property array<string, mixed>|null $output_metadata
 * @property list<string>|null $validation_errors
 * @property array<string, mixed>|null $error_information
 * @property array<string, mixed>|null $metadata
 */
final class AgentExecutionStepRecord extends Model
{
    use HasUuids;

    protected $table = 'agent_execution_steps';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'agent_execution_id',
        'step_number',
        'action_type',
        'tool_name',
        'status',
        'failure_category',
        'started_at',
        'completed_at',
        'duration_ms',
        'input_metadata',
        'output_metadata',
        'validation_errors',
        'error_information',
        'metadata',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'action_type' => 'tool',
        'status' => 'running',
        'duration_ms' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'input_metadata' => 'array',
            'output_metadata' => 'array',
            'validation_errors' => 'array',
            'error_information' => 'array',
            'metadata' => 'array',
            'duration_ms' => 'integer',
            'step_number' => 'integer',
        ];
    }

    /** @return BelongsTo<AgentExecutionRecord, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(AgentExecutionRecord::class, 'agent_execution_id');
    }
}
