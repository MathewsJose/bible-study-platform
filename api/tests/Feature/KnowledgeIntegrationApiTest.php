<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('knowledge_service.base_url', 'http://knowledge.test');
        config()->set('knowledge_service.ai_rate_limit_per_minute', 1);

        Http::preventStrayRequests();
    }

    public function test_search_endpoint_forwards_to_knowledge_service_with_correlation_id(): void
    {
        Http::fake([
            'knowledge.test/api/v1/knowledge/search*' => Http::response([
                'data' => [
                    'query' => 'Word became flesh',
                    'results' => [
                        [
                            'id' => 'doc-1',
                            'reference' => 'John 1:14',
                            'title' => 'The Word became flesh',
                            'source_type' => 'bible_verse',
                            'content' => 'The Word became flesh.',
                            'score' => 0.91,
                        ],
                    ],
                ],
                'meta' => ['total' => 1],
            ]),
        ]);

        $this->withHeader('X-Request-ID', 'request-123')
            ->getJson('/v1/knowledge/search?query=Word%20became%20flesh&book=John&chapter=1&limit=5')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request_id', 'request-123')
            ->assertJsonPath('data.data.results.0.reference', 'John 1:14');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Request-ID', 'request-123')
            && str_contains($request->url(), '/api/v1/knowledge/search')
            && $request['book'] === 'John');
    }

    public function test_reference_not_found_is_mapped_to_public_error_contract(): void
    {
        Http::fake([
            'knowledge.test/api/v1/knowledge/reference/*' => Http::response([
                'message' => 'Reference not found.',
                'errors' => ['reference' => ['No match.']],
            ], 404),
        ]);

        $this->getJson('/v1/knowledge/reference/'.rawurlencode('CCC 999999'))
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Reference not found.')
            ->assertJsonStructure(['errors' => ['reference']]);
    }

    public function test_answer_endpoint_requires_authentication_and_forwards_when_authenticated(): void
    {
        $this->postJson('/v1/knowledge/answer', ['question' => 'Why did Jesus become man?'])
            ->assertUnauthorized();

        Http::fake([
            'knowledge.test/api/v1/knowledge/answer' => Http::response([
                'data' => [
                    'answer' => 'For our salvation [1].',
                    'citations' => [['reference' => 'CCC 457']],
                    'confidence' => ['score' => 0.8],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('knowledge-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/knowledge/answer', ['question' => 'Why did Jesus become man?'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.answer', 'For our salvation [1].');
    }

    public function test_agent_endpoint_is_rate_limited_for_expensive_operations(): void
    {
        Http::fake([
            'knowledge.test/api/v1/knowledge/agents/run' => Http::response([
                'data' => ['status' => 'completed', 'answer' => 'Done.'],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('knowledge-agent-test')->plainTextToken;

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->postJson('/v1/knowledge/agents/run', ['input' => 'Explain John 1:14'])
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson('/v1/knowledge/agents/run', ['input' => 'Explain John 3:16'])
            ->assertStatus(429);
    }

    public function test_agent_trace_endpoint_requires_authentication_and_forwards_request(): void
    {
        $this->getJson('/v1/knowledge/agents/executions/trace-1')
            ->assertUnauthorized();

        Http::fake([
            'knowledge.test/api/v1/knowledge/agents/executions/trace-1' => Http::response([
                'data' => [
                    'execution' => ['id' => 'trace-1', 'status' => 'completed'],
                    'steps' => [['tool_name' => 'answer_generation']],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('knowledge-trace-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/knowledge/agents/executions/trace-1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.execution.id', 'trace-1');
    }

    public function test_agent_replay_endpoints_require_authentication_and_forward_request(): void
    {
        config()->set('knowledge_service.ai_rate_limit_per_minute', 10);

        $this->postJson('/v1/knowledge/agents/executions/trace-1/replay', ['dry_run' => true])
            ->assertUnauthorized();

        Http::fake([
            'knowledge.test/api/v1/knowledge/agents/executions/trace-1/replay' => Http::response([
                'data' => [
                    'id' => 'replay-1',
                    'original_execution_id' => 'trace-1',
                    'status' => 'completed',
                    'comparison_status' => 'MATCH',
                ],
            ], 202),
            'knowledge.test/api/v1/knowledge/agent-replays/replay-1' => Http::response([
                'data' => [
                    'id' => 'replay-1',
                    'status' => 'completed',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('knowledge-replay-test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Request-ID' => 'replay-request'];

        $this->withHeaders($headers)
            ->postJson('/v1/knowledge/agents/executions/trace-1/replay', ['dry_run' => true])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.id', 'replay-1');

        $statusUser = User::factory()->create();
        $statusToken = $statusUser->createToken('knowledge-replay-status-test')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$statusToken, 'X-Request-ID' => 'replay-request'])
            ->getJson('/v1/knowledge/agent-replays/replay-1')
            ->assertOk()
            ->assertJsonPath('data.data.id', 'replay-1');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Request-ID', 'replay-request')
            && str_contains($request->url(), '/api/v1/knowledge/agents/executions/trace-1/replay')
            && $request['dry_run'] === true);
    }

    public function test_service_connection_failure_returns_unavailable_error(): void
    {
        Http::fake([
            'knowledge.test/api/v1/knowledge/search*' => Http::failedConnection(),
        ]);

        $this->getJson('/v1/knowledge/search?query=incarnation')
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Knowledge service unavailable.');
    }
}
