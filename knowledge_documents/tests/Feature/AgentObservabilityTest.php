<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentEvaluationRunRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionStepRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

final class ObservabilityTestProvider implements LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        return new LLMCompletionResponse(
            content: 'Jesus became man for our salvation [1].',
            provider: 'observability-provider',
            model: $request->model,
            latencyMs: 7,
            promptTokens: 30,
            completionTokens: 9,
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
        return ['provider' => 'observability-provider'];
    }

    public function identifier(): string
    {
        return 'observability-provider';
    }
}

function observabilitySource(): string
{
    return 'Sprint 17 Observability Corpus';
}

beforeEach(function (): void {
    config()->set('ai.provider', 'null');
    config()->set('ai.model', 'observability-model');
    config()->set('agent_observability.tracing.enabled', true);
    config()->set('agent_observability.tracing.store_inputs', false);
    config()->set('agent_observability.tracing.store_outputs', false);
    config()->set('agent_observability.trace_api.token', '');
    config()->set('retrieval.profiles.ai_answer.use_vector', false);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.use_expansion', false);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 0);

    KnowledgeDocumentRecord::query()->where('source_name', observabilitySource())->delete();
});

it('persists agent executions steps ai metrics and correlation ids', function (): void {
    app()->instance(LLMProviderInterface::class, new ObservabilityTestProvider());

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => observabilitySource(),
        'reference' => 'CCC 457 Observability',
        'title' => 'Why the Word became Flesh',
        'content' => 'Jesus became man and the Word became flesh for us in order to save us.',
    ]);

    $response = postJson('/api/v1/knowledge/agents/run', [
        'input' => 'Jesus became man?',
        'allowed_tools' => ['answer_generation'],
        'filters' => ['source_name' => observabilitySource()],
    ], ['X-Request-ID' => 'platform-request-17'])
        ->assertOk()
        ->assertJsonPath('data.request_id', 'platform-request-17')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.tool_results.0.tool', 'answer_generation');

    $traceId = (string) $response->json('data.trace_id');
    $execution = AgentExecutionRecord::query()->find($traceId);

    expect($execution)->toBeInstanceOf(AgentExecutionRecord::class)
        ->and($execution->request_id)->toBe('platform-request-17')
        ->and($execution->status)->toBe('completed')
        ->and($execution->provider)->toBe('observability-provider')
        ->and($execution->model)->toBe('observability-model')
        ->and($execution->prompt_tokens)->toBe(30)
        ->and($execution->completion_tokens)->toBe(9)
        ->and($execution->total_tokens)->toBe(39)
        ->and($execution->answer_metrics)->toHaveKey('citation_count');

    $step = AgentExecutionStepRecord::query()->where('agent_execution_id', $traceId)->first();

    expect($step)->toBeInstanceOf(AgentExecutionStepRecord::class)
        ->and($step->tool_name)->toBe('answer_generation')
        ->and($step->status)->toBe('success')
        ->and($step->output_metadata)->toHaveKey('diagnostics')
        ->and($step->output_metadata)->not->toHaveKey('data');
});

it('redacts persisted inputs when payload retention is enabled', function (): void {
    config()->set('agent_observability.tracing.store_inputs', true);
    app()->instance(LLMProviderInterface::class, new ObservabilityTestProvider());

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => observabilitySource(),
        'reference' => 'CCC 457 Redaction',
        'content' => 'Jesus became man for our salvation.',
    ]);

    $response = postJson('/api/v1/knowledge/agents/run', [
        'input' => 'Jesus became man? Bearer secret-token-value',
        'allowed_tools' => ['answer_generation'],
        'filters' => ['source_name' => observabilitySource(), 'token' => 'secret'],
        'metadata' => ['token' => 'secret'],
    ])->assertOk();

    $execution = AgentExecutionRecord::query()->find((string) $response->json('data.trace_id'));

    expect($execution?->input_metadata['input'] ?? '')->toContain('[REDACTED]')
        ->and(json_encode($execution?->input_metadata, JSON_THROW_ON_ERROR))->not->toContain('secret-token-value');
});

it('retrieves traces through protected api and cli', function (): void {
    app()->instance(LLMProviderInterface::class, new ObservabilityTestProvider());

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => observabilitySource(),
        'reference' => 'CCC 457 Trace API',
        'content' => 'Jesus became man for our salvation.',
    ]);

    $traceId = (string) postJson('/api/v1/knowledge/agents/run', [
        'input' => 'Jesus became man?',
        'allowed_tools' => ['answer_generation'],
        'filters' => ['source_name' => observabilitySource()],
    ])->json('data.trace_id');

    config()->set('agent_observability.trace_api.token', 'trace-secret');

    getJson('/api/v1/knowledge/agents/executions/'.$traceId)
        ->assertForbidden();

    getJson('/api/v1/knowledge/agents/executions/'.$traceId, ['Authorization' => 'Bearer trace-secret'])
        ->assertOk()
        ->assertJsonPath('data.execution.id', $traceId)
        ->assertJsonPath('data.steps.0.tool_name', 'answer_generation');

    $status = Artisan::call('agent:trace', ['--id' => $traceId]);
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Agent Trace')
        ->and($output)->toContain('answer_generation');
});

it('reports a migration hint when trace tables are unavailable', function (): void {
    $repository = \Mockery::mock(AgentTraceRepositoryInterface::class);
    $repository->shouldReceive('health')
        ->once()
        ->with(7)
        ->andReturn([
            'schema_available' => false,
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_latency_ms' => 0.0,
            'average_steps' => 0.0,
            'average_tool_calls' => 0.0,
            'most_used_tools' => [],
            'most_failed_tools' => [],
            'recent_failures' => [],
        ]);

    app()->instance(AgentTraceRepositoryInterface::class, $repository);

    $status = Artisan::call('agent:health', ['--days' => 7]);
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Trace tables are not available yet.')
        ->and($output)->toContain('php artisan migrate');
});

it('persists evaluation runs detects regressions and prunes old traces', function (): void {
    $firstStatus = Artisan::call('agent:evaluate', ['--save' => true, '--name' => 'observability-eval']);
    $secondStatus = Artisan::call('agent:evaluate', ['--save' => true, '--name' => 'observability-eval']);

    expect($firstStatus)->toBe(Command::SUCCESS)
        ->and($secondStatus)->toBe(Command::SUCCESS)
        ->and(AgentEvaluationRunRecord::query()->where('name', 'observability-eval')->count())->toBe(2)
        ->and(AgentEvaluationRunRecord::query()->latest()->first()?->dataset_version)->toBe('agent-v1');

    $old = AgentExecutionRecord::query()->create([
        'request_id' => 'old-request',
        'profile' => 'catholic_research',
        'status' => 'completed',
        'started_at' => CarbonImmutable::now()->subDays(45),
        'completed_at' => CarbonImmutable::now()->subDays(45),
    ]);
    $old->forceFill(['created_at' => CarbonImmutable::now()->subDays(45), 'updated_at' => CarbonImmutable::now()->subDays(45)])->save();

    $pruneStatus = Artisan::call('agent:traces:prune', ['--days' => 30, '--limit' => 10]);

    expect($pruneStatus)->toBe(Command::SUCCESS)
        ->and(AgentExecutionRecord::query()->find($old->id))->toBeNull();
});
