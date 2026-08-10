<?php

declare(strict_types=1);

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Agents\DTOs\AgentAction;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use App\Application\Knowledge\Agents\Services\AgentGuardrailPolicy;
use App\Application\Knowledge\Agents\Services\AgentProfileRepository;
use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use App\Application\Knowledge\Agents\Services\DeterministicAgentPlanner;
use App\Application\Knowledge\Agents\Services\ToolInputValidator;
use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\postJson;

final class AgentTestProvider implements LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        return new LLMCompletionResponse(
            content: 'Jesus became man for our salvation [1].',
            provider: 'agent-test-provider',
            model: $request->model,
            latencyMs: 5,
            promptTokens: 20,
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
        return ['provider' => 'agent-test-provider'];
    }

    public function identifier(): string
    {
        return 'agent-test-provider';
    }
}

final readonly class DuplicateAgentTool implements ToolInterface
{
    public function name(): string
    {
        return 'duplicate_tool';
    }

    public function displayName(): string
    {
        return 'Duplicate Tool';
    }

    public function description(): string
    {
        return 'Test duplicate tool.';
    }

    public function inputSchema(): array
    {
        return ['properties' => [], 'rules' => []];
    }

    public function outputSchema(): array
    {
        return ['properties' => []];
    }

    public function permissions(): array
    {
        return ['knowledge:read'];
    }

    public function timeoutSeconds(): int
    {
        return 1;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function execute(ToolInvocation $invocation): ToolResult
    {
        return new ToolResult($this->name(), true, 'success');
    }
}

function agentTestSource(): string
{
    return 'Sprint 15 Agent Test Corpus';
}

beforeEach(function (): void {
    config()->set('ai.provider', 'null');
    config()->set('ai.model', 'agent-test-model');
    config()->set('retrieval.profiles.ai_answer.use_vector', false);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.use_expansion', false);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 0);
    config()->set('retrieval.profiles.research.use_vector', false);
    config()->set('retrieval.profiles.research.use_lexical', true);
    config()->set('retrieval.profiles.research.use_expansion', false);
    config()->set('retrieval.profiles.research.graph_depth', 0);

    KnowledgeDocumentRecord::query()
        ->where('source_name', agentTestSource())
        ->delete();
});

it('lists registered read-only tools and rejects duplicate names', function (): void {
    $registry = app(AgentToolRegistry::class);

    expect($registry->names())->toContain(
        'bible_search',
        'scripture_reference',
        'catechism_search',
        'church_father_search',
        'knowledge_graph',
        'advanced_retrieval',
        'answer_generation',
    );

    $manualRegistry = new AgentToolRegistry();
    $manualRegistry->register(new DuplicateAgentTool());
    $manualRegistry->register(new DuplicateAgentTool());
})->throws(InvalidArgumentException::class, 'already registered');

it('validates tool input and rejects arbitrary parameters', function (): void {
    $tool = app(AgentToolRegistry::class)->resolve('bible_search');
    $errors = app(ToolInputValidator::class)->errors([
        'query' => '',
        'shell' => 'php artisan migrate',
    ], $tool->inputSchema());

    expect($errors)->not->toBeEmpty()
        ->and(implode(' ', $errors))->toContain('shell');
});

it('plans deterministic multi source workflows', function (): void {
    $profile = app(AgentProfileRepository::class)->resolve('catholic_research');
    $state = AgentState::start('agent-test', new AgentRequest('Explain why Jesus became man according to Scripture, Catechism, and the Fathers.'), $profile);
    $plan = app(DeterministicAgentPlanner::class)->plan($state);

    expect(array_map(static fn (AgentAction $action): string => $action->tool, $plan->actions))
        ->toContain('advanced_retrieval', 'answer_generation');
});

it('blocks disallowed tools before execution', function (): void {
    $profile = app(AgentProfileRepository::class)->resolve('scripture_research');
    $state = AgentState::start('agent-test', new AgentRequest('What does CCC 456 say?', 'scripture_research'), $profile);
    $tool = app(AgentToolRegistry::class)->resolve('answer_generation');
    $violations = app(AgentGuardrailPolicy::class)->violations(
        $state,
        new AgentAction('answer_generation', ['question' => 'What does CCC 456 say?'], 'test'),
        $tool,
    );

    expect(implode(' ', $violations))->toContain('not allowed');
});

it('executes agent runs through the api with structured trace', function (): void {
    app()->instance(LLMProviderInterface::class, new AgentTestProvider());

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => agentTestSource(),
        'reference' => 'CCC 457 Agent',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for us in order to save us by reconciling us with God.',
    ]);

    Event::fake();

    postJson('/api/agents/run', [
        'input' => 'Why did Jesus become man?',
        'profile' => 'catholic_research',
        'filters' => ['source_name' => agentTestSource()],
        'allowed_tools' => ['answer_generation'],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.tool_results.0.tool', 'answer_generation')
        ->assertJsonPath('data.tool_results.0.data.provider', 'agent-test-provider')
        ->assertJsonStructure([
            'data' => [
                'agent_id',
                'request_id',
                'status',
                'answer',
                'tool_results',
                'trace',
                'diagnostics' => ['profile', 'duration_ms', 'steps', 'tool_calls', 'tools_used'],
            ],
        ]);
});

it('prints agent diagnostics from artisan commands', function (): void {
    $toolsStatus = Artisan::call('agent:tools');
    $toolsOutput = Artisan::output();
    $healthStatus = Artisan::call('agent:health');
    $healthOutput = Artisan::output();
    $evaluationStatus = Artisan::call('agent:evaluate');
    $evaluationOutput = Artisan::output();

    expect($toolsStatus)->toBe(Command::SUCCESS)
        ->and($toolsOutput)->toContain('Bible Search')
        ->and($healthStatus)->toBe(Command::SUCCESS)
        ->and($healthOutput)->toContain('Agent Framework Health')
        ->and($evaluationStatus)->toBe(Command::SUCCESS)
        ->and($evaluationOutput)->toContain('Agent Evaluation');
});
