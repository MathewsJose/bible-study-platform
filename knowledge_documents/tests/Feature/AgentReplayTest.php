<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Agents\Replay\Services\CorpusFingerprintService;
use App\Application\Knowledge\Agents\Replay\Services\DocumentFingerprintService;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentReplayRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

final class ReplayTestProvider implements LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        return new LLMCompletionResponse(
            content: 'The Word became flesh for our salvation [1].',
            provider: 'replay-provider',
            model: $request->model,
            latencyMs: 3,
            promptTokens: 12,
            completionTokens: 8,
        );
    }

    public function stream(LLMCompletionRequest $request): iterable
    {
        yield $this->complete($request)->content;
    }

    public function countTokens(string $text): int
    {
        return str_word_count($text);
    }

    public function metadata(): array
    {
        return ['provider' => 'replay-provider'];
    }

    public function identifier(): string
    {
        return 'replay-provider';
    }
}

function replaySource(): string
{
    return 'Sprint 18 Replay Corpus';
}

beforeEach(function (): void {
    config()->set('ai.provider', 'null');
    config()->set('ai.model', 'replay-model');
    config()->set('agent_observability.tracing.enabled', true);
    config()->set('agent_observability.tracing.store_inputs', true);
    config()->set('agent_observability.tracing.store_outputs', true);
    config()->set('agent_observability.trace_api.token', '');
    config()->set('retrieval.profiles.ai_answer.use_vector', false);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.use_expansion', false);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 0);

    app()->instance(LLMProviderInterface::class, new ReplayTestProvider());

    KnowledgeDocumentRecord::query()->where('source_name', replaySource())->delete();
});

it('generates deterministic document and corpus fingerprints', function (): void {
    $document = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => replaySource(),
        'reference' => 'CCC 456 Replay',
        'content' => 'The Word became flesh for us in order to save us.',
    ]);

    $documentFingerprint = app(DocumentFingerprintService::class)->fingerprint($document);
    $corpusFingerprint = app(CorpusFingerprintService::class)->fingerprint();

    expect($documentFingerprint['hash'])->toBeString()->toHaveLength(64)
        ->and($corpusFingerprint['hash'])->toBeString()->toHaveLength(64)
        ->and($corpusFingerprint['document_count'])->toBeGreaterThanOrEqual(1);
});

it('persists replay metadata and supports dry run comparison', function (): void {
    $traceId = createReplayReadyExecution();

    $execution = AgentExecutionRecord::query()->find($traceId);
    expect($execution?->metadata['replay']['execution_fingerprint']['hash'] ?? null)->toBeString();

    $status = Artisan::call('agent:replay', ['--id' => $traceId, '--dry-run' => true, '--compare' => true]);
    $output = Artisan::output();
    $replay = AgentReplayRecord::query()->latest()->first();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Agent Replay')
        ->and($replay)->toBeInstanceOf(AgentReplayRecord::class)
        ->and($replay?->status)->toBe('completed')
        ->and($replay?->comparison_status)->toBe('MATCH')
        ->and($replay?->comparison['tool_sequence_status'] ?? null)->toBe('NOT_REPLAYED');
});

it('detects strict replay configuration divergence', function (): void {
    $traceId = createReplayReadyExecution();

    config()->set('retrieval.profiles.research.top_k', 99);

    $status = Artisan::call('agent:replay', ['--id' => $traceId, '--strict' => true, '--dry-run' => true]);
    $replay = AgentReplayRecord::query()->latest()->first();

    expect($status)->toBe(Command::FAILURE)
        ->and($replay)->toBeInstanceOf(AgentReplayRecord::class)
        ->and($replay?->status)->toBe('failed')
        ->and($replay?->comparison_status)->toBe('STRICT_MISMATCH');
});

it('runs a live replay and compares the replayed execution', function (): void {
    $traceId = createReplayReadyExecution();

    $status = Artisan::call('agent:replay', ['--id' => $traceId, '--compare' => true]);
    $replay = AgentReplayRecord::query()->latest()->first();

    expect($status)->toBe(Command::SUCCESS)
        ->and($replay)->toBeInstanceOf(AgentReplayRecord::class)
        ->and($replay?->status)->toBe('completed')
        ->and($replay?->replay_execution_id)->not->toBeNull()
        ->and($replay?->comparison['tool_sequence_status'] ?? null)->toBe('MATCH')
        ->and($replay?->comparison['answer']['status'] ?? null)->toBe('IDENTICAL');
});

it('refuses live replay when original inputs were not retained', function (): void {
    config()->set('agent_observability.tracing.store_inputs', false);

    $traceId = createReplayReadyExecution();
    $before = AgentReplayRecord::query()->count();

    $status = Artisan::call('agent:replay', ['--id' => $traceId]);
    $output = Artisan::output();

    expect($status)->toBe(Command::FAILURE)
        ->and($output)->toContain('AGENT_TRACE_STORE_INPUTS=true')
        ->and(AgentReplayRecord::query()->count())->toBe($before);
});

it('protects replay api and returns replay status', function (): void {
    $traceId = createReplayReadyExecution();

    config()->set('agent_observability.trace_api.token', 'replay-secret');

    postJson('/api/v1/knowledge/agents/executions/'.$traceId.'/replay', ['dry_run' => true])
        ->assertForbidden();

    $response = postJson('/api/v1/knowledge/agents/executions/'.$traceId.'/replay', ['dry_run' => true], ['Authorization' => 'Bearer replay-secret'])
        ->assertAccepted()
        ->assertJsonPath('data.original_execution_id', $traceId);

    getJson('/api/v1/knowledge/agent-replays/'.$response->json('data.id'), ['Authorization' => 'Bearer replay-secret'])
        ->assertOk()
        ->assertJsonPath('data.id', $response->json('data.id'));
});

function createReplayReadyExecution(): string
{
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => replaySource(),
        'reference' => 'CCC 457 Replay',
        'title' => 'Why the Word became Flesh',
        'content' => 'Jesus became man and the Word became flesh for us in order to save us.',
    ]);

    return (string) postJson('/api/v1/knowledge/agents/run', [
        'input' => 'Why did Jesus become man?',
        'allowed_tools' => ['answer_generation'],
        'filters' => ['source_name' => replaySource()],
    ], ['X-Request-ID' => 'replay-request-18'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->json('data.trace_id');
}
