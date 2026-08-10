<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Replay\Services;

use App\Application\Knowledge\Agents\Contracts\AgentInterface;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentReplayRecord;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use RuntimeException;

final readonly class AgentReplayService
{
    public function __construct(
        private AgentInterface $agent,
        private ExecutionFingerprintService $fingerprints,
        private ReplayComparisonService $comparison,
    ) {}

    public function replay(
        string $executionId,
        bool $strict = false,
        bool $dryRun = false,
        ?string $provider = null,
        ?string $model = null,
    ): AgentReplayRecord {
        $original = AgentExecutionRecord::query()->with('steps')->find($executionId);

        if (! $original instanceof AgentExecutionRecord) {
            throw new RuntimeException('Agent execution trace not found.');
        }

        $started = microtime(true);
        $currentFingerprint = $this->fingerprints->current($original);
        $originalFingerprint = $this->originalFingerprint($original);
        $request = $dryRun ? null : $this->requestFromTrace($original);

        $replay = AgentReplayRecord::query()->create([
            'original_execution_id' => $original->id,
            'mode' => $dryRun ? 'dry-run' : 'live',
            'status' => 'running',
            'strict' => $strict,
            'dry_run' => $dryRun,
            'started_at' => CarbonImmutable::now(),
            'original_fingerprint' => $originalFingerprint,
            'replay_fingerprint' => $currentFingerprint,
            'corpus_snapshot' => $currentFingerprint['corpus'] ?? [],
            'configuration_snapshot' => $currentFingerprint['payload'] ?? [],
            'metadata' => [
                'provider_override' => $provider,
                'model_override' => $model,
                'exact_model_replay_guaranteed' => false,
            ],
        ]);

        if ($strict && $this->strictMismatch($originalFingerprint, $currentFingerprint)) {
            return $this->completeWithFailure($replay, $started, 'Strict replay failed because execution or corpus fingerprints do not match.');
        }

        if ($dryRun) {
            $comparison = $this->comparison->compare($original, null, $currentFingerprint);

            return $this->complete($replay, $started, null, $comparison->toArray());
        }

        $originalProvider = config('ai.provider');
        $originalModel = config('ai.model');

        if (! $request instanceof AgentRequest) {
            throw new RuntimeException('Replay request could not be reconstructed.');
        }

        try {
            if ($provider !== null) {
                Config::set('ai.provider', $provider);
            }

            if ($model !== null) {
                Config::set('ai.model', $model);
            }

            $response = $this->agent->execute($request);
        } finally {
            Config::set('ai.provider', $originalProvider);
            Config::set('ai.model', $originalModel);
        }

        $replayExecution = AgentExecutionRecord::query()->with('steps')->find($response->traceId ?? $response->agentId);
        $comparison = $this->comparison->compare($original, $replayExecution, $currentFingerprint);

        return $this->complete($replay, $started, $replayExecution?->id, $comparison->toArray());
    }

    private function requestFromTrace(AgentExecutionRecord $execution): AgentRequest
    {
        $input = $execution->input_metadata['input'] ?? null;

        if (! is_string($input) || $input === '') {
            throw new RuntimeException('Replay requires AGENT_TRACE_STORE_INPUTS=true on the original execution.');
        }

        $filters = $execution->input_metadata['filters'] ?? [];
        $metadata = $execution->input_metadata['metadata'] ?? [];

        return new AgentRequest(
            input: $input,
            profile: $execution->profile,
            filters: is_array($filters) && ! array_is_list($filters) ? $filters : [],
            allowedTools: $this->toolSequence($execution),
            metadata: is_array($metadata) ? $metadata : [],
            requestId: 'replay-'.$execution->request_id,
        );
    }

    /** @return list<string> */
    private function toolSequence(AgentExecutionRecord $execution): array
    {
        return $execution->steps
            ->pluck('tool_name')
            ->map(static fn (mixed $tool): string => (string) $tool)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function originalFingerprint(AgentExecutionRecord $execution): array
    {
        $metadata = is_array($execution->metadata) ? $execution->metadata : [];
        $replay = is_array($metadata['replay'] ?? null) ? $metadata['replay'] : [];

        return is_array($replay['execution_fingerprint'] ?? null) ? $replay['execution_fingerprint'] : [];
    }

    private function strictMismatch(array $original, array $current): bool
    {
        return ($original['hash'] ?? null) === null
            || ($current['hash'] ?? null) === null
            || $original['hash'] !== $current['hash']
            || ($original['corpus']['hash'] ?? null) !== ($current['corpus']['hash'] ?? null);
    }

    private function complete(AgentReplayRecord $replay, float $started, ?string $replayExecutionId, array $comparison): AgentReplayRecord
    {
        $replay->update([
            'replay_execution_id' => $replayExecutionId,
            'status' => 'completed',
            'comparison_status' => (string) ($comparison['status'] ?? 'UNKNOWN'),
            'completed_at' => CarbonImmutable::now(),
            'duration_ms' => $this->elapsedMs($started),
            'comparison' => $comparison,
            'divergence_summary' => [
                'status' => $comparison['status'] ?? 'UNKNOWN',
                'possible_causes' => $comparison['possible_causes'] ?? [],
            ],
        ]);

        return $replay->refresh();
    }

    private function completeWithFailure(AgentReplayRecord $replay, float $started, string $message): AgentReplayRecord
    {
        $replay->update([
            'status' => 'failed',
            'comparison_status' => 'STRICT_MISMATCH',
            'completed_at' => CarbonImmutable::now(),
            'duration_ms' => $this->elapsedMs($started),
            'error_information' => ['message' => $message],
            'divergence_summary' => [
                'status' => 'STRICT_MISMATCH',
                'possible_causes' => ['Corpus, configuration, provider, model, prompt, retrieval, or tool registry changed.'],
            ],
        ]);

        return $replay->refresh();
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
