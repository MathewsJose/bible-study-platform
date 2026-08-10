<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Agents\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $original_execution_id
 * @property string|null $replay_execution_id
 * @property string $mode
 * @property string $status
 * @property string|null $comparison_status
 * @property bool $strict
 * @property bool $dry_run
 * @property \Carbon\CarbonImmutable|null $started_at
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property int $duration_ms
 * @property array<string, mixed>|null $original_fingerprint
 * @property array<string, mixed>|null $replay_fingerprint
 * @property array<string, mixed>|null $corpus_snapshot
 * @property array<string, mixed>|null $configuration_snapshot
 * @property array<string, mixed>|null $comparison
 * @property array<string, mixed>|null $divergence_summary
 * @property array<string, mixed>|null $error_information
 * @property array<string, mixed>|null $metadata
 */
final class AgentReplayRecord extends Model
{
    use HasUuids;

    protected $table = 'agent_replays';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'original_execution_id',
        'replay_execution_id',
        'mode',
        'status',
        'comparison_status',
        'strict',
        'dry_run',
        'started_at',
        'completed_at',
        'duration_ms',
        'original_fingerprint',
        'replay_fingerprint',
        'corpus_snapshot',
        'configuration_snapshot',
        'comparison',
        'divergence_summary',
        'error_information',
        'metadata',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'mode' => 'live',
        'status' => 'running',
        'strict' => false,
        'dry_run' => false,
        'duration_ms' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'strict' => 'boolean',
            'dry_run' => 'boolean',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'duration_ms' => 'integer',
            'original_fingerprint' => 'array',
            'replay_fingerprint' => 'array',
            'corpus_snapshot' => 'array',
            'configuration_snapshot' => 'array',
            'comparison' => 'array',
            'divergence_summary' => 'array',
            'error_information' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AgentExecutionRecord, $this> */
    public function originalExecution(): BelongsTo
    {
        return $this->belongsTo(AgentExecutionRecord::class, 'original_execution_id');
    }

    /** @return BelongsTo<AgentExecutionRecord, $this> */
    public function replayExecution(): BelongsTo
    {
        return $this->belongsTo(AgentExecutionRecord::class, 'replay_execution_id');
    }
}
