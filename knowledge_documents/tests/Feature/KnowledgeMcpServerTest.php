<?php

declare(strict_types=1);

use App\Application\Knowledge\Mcp\Services\McpToolCatalog;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionStepRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

use function Pest\Laravel\postJson;

function mcpHeaders(): array
{
    return [
        'Authorization' => 'Bearer mcp-test-token',
        'X-Request-ID' => 'mcp-request-19',
    ];
}

function mcpPayload(string $method, array $params = [], int|string $id = 1): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => $method,
        'params' => $params,
    ];
}

beforeEach(function (): void {
    config()->set('mcp_knowledge.enabled', true);
    config()->set('mcp_knowledge.token', 'mcp-test-token');
    config()->set('mcp_knowledge.rate_limit_per_minute', 60);
    config()->set('agent_observability.tracing.enabled', true);
    config()->set('agent_observability.tracing.store_inputs', false);
    config()->set('agent_observability.tracing.store_outputs', false);

    KnowledgeDocumentRecord::query()->where('source_name', 'MCP Test Bible')->delete();
});

it('rejects unauthenticated and disabled mcp access', function (): void {
    postJson('/mcp/knowledge', mcpPayload('tools/list'))
        ->assertUnauthorized()
        ->assertJsonPath('error.message', 'Unauthorized MCP request.');

    config()->set('mcp_knowledge.enabled', false);

    postJson('/mcp/knowledge', mcpPayload('tools/list'), mcpHeaders())
        ->assertStatus(503)
        ->assertJsonPath('error.message', 'MCP server is disabled.');
});

it('initializes and lists only read only allowed tools with schemas', function (): void {
    postJson('/mcp/knowledge', mcpPayload('initialize', [
        'protocolVersion' => '2025-06-18',
        'clientInfo' => ['name' => 'pest-mcp-client', 'version' => '1.0.0'],
        'capabilities' => [],
    ]), mcpHeaders())
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Catholic Bible Knowledge MCP');

    $response = postJson('/mcp/knowledge', mcpPayload('tools/list'), mcpHeaders())
        ->assertOk()
        ->assertJsonPath('result.tools.0.annotations.readOnlyHint', true);

    $tools = collect($response->json('result.tools'));

    expect($tools->pluck('name')->all())->toBe([
        'bible_search',
        'scripture_reference',
        'catechism_search',
        'church_father_search',
        'knowledge_graph',
        'advanced_retrieval',
    ])
        ->and($tools->firstWhere('name', 'bible_search')['inputSchema']['required'])->toContain('query')
        ->and($tools->contains(fn (array $tool): bool => ($tool['annotations']['destructiveHint'] ?? true) !== false))->toBeFalse();
});

it('invokes bible search through the mcp protocol and persists a trace', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'MCP Test Bible',
        'reference' => 'John 1:14 MCP',
        'title' => 'John 1:14',
        'content' => 'The Word became flesh and dwelt among us.',
    ]);

    $response = postJson('/mcp/knowledge', mcpPayload('tools/call', [
        'name' => 'bible_search',
        'arguments' => [
            'query' => 'Word became flesh',
            'limit' => 5,
        ],
    ], 'call-1'), mcpHeaders())
        ->assertOk()
        ->assertJsonPath('result.structuredContent.tool', 'bible_search')
        ->assertJsonPath('result.structuredContent.successful', true);

    expect((int) $response->json('result.structuredContent.data.total'))->toBeGreaterThanOrEqual(1)
        ->and(AgentExecutionRecord::query()->where('profile', 'mcp')->where('request_id', 'mcp-request-19')->exists())->toBeTrue()
        ->and(AgentExecutionStepRecord::query()->where('tool_name', 'bible_search')->where('action_type', 'mcp_tool')->exists())->toBeTrue();
});

it('returns mcp errors for unknown tools invalid arguments and malformed json rpc', function (): void {
    postJson('/mcp/knowledge', mcpPayload('tools/call', [
        'name' => 'shell_exec',
        'arguments' => ['command' => 'whoami'],
    ]), mcpHeaders())
        ->assertOk()
        ->assertJsonPath('error.code', -32602)
        ->assertJsonPath('error.message', 'Tool [shell_exec] not found.');

    postJson('/mcp/knowledge', mcpPayload('tools/call', [
        'name' => 'bible_search',
        'arguments' => ['query' => 'John', 'path' => 'database/database.sqlite'],
    ]), mcpHeaders())
        ->assertOk()
        ->assertJsonPath('result.isError', true)
        ->assertJsonMissing(['database/database.sqlite']);

    postJson('/mcp/knowledge', ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'missing/method'], mcpHeaders())
        ->assertOk()
        ->assertJsonPath('error.code', -32601);
});

it('enforces mcp rate limiting', function (): void {
    config()->set('mcp_knowledge.rate_limit_per_minute', 1);

    postJson('/mcp/knowledge', mcpPayload('tools/list', id: 'first'), mcpHeaders())
        ->assertOk();

    postJson('/mcp/knowledge', mcpPayload('tools/list', id: 'second'), mcpHeaders())
        ->assertStatus(429);
});

it('keeps mcp catalog schemas aligned with internal tool contracts', function (): void {
    $catalog = app(McpToolCatalog::class);

    foreach ($catalog->all() as $definition) {
        $internal = app(\App\Application\Knowledge\Agents\Services\AgentToolRegistry::class)->resolve($definition->name);
        $internalProperties = array_keys((array) ($internal->inputSchema()['properties'] ?? []));
        $mcpProperties = array_keys((array) ($definition->inputSchema['properties'] ?? []));

        expect($definition->readOnly)->toBeTrue()
            ->and($definition->inputSchema['additionalProperties'])->toBeFalse()
            ->and($mcpProperties)->toBe($internalProperties);
    }
});

it('mcp diagnostics commands are generated from the catalog', function (): void {
    $this->artisan('mcp:health')
        ->expectsOutputToContain('MCP Server Health')
        ->expectsOutputToContain('Registered tools: 6')
        ->assertSuccessful();

    $this->artisan('mcp:tools')
        ->expectsOutputToContain('bible_search')
        ->expectsOutputToContain('Read-only tools: 6')
        ->assertSuccessful();
});
