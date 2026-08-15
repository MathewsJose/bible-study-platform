<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Answering\Exceptions\LlmProviderCapabilityException;
use App\Application\Knowledge\Answering\Services\AnswerQuestionService;
use App\Application\Knowledge\Answering\Services\LlmGateway;
use App\Application\Knowledge\Answering\Services\LlmModelRouter;
use App\Application\Knowledge\Security\Exceptions\AISecurityException;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\AiEvaluationRunRecord;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Facades\Artisan;

final class LlmPlatformTestProvider implements LLMProviderInterface
{
    public ?LLMCompletionRequest $lastRequest = null;

    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $this->lastRequest = $request;

        return new LLMCompletionResponse(
            content: 'The Word became flesh for our salvation [1].',
            provider: $this->identifier(),
            model: $request->model,
            latencyMs: 4,
            promptTokens: 10,
            completionTokens: 8,
            metadata: ['request_id' => 'test-request'],
            finishReason: 'stop',
            usage: ['input_tokens' => 10, 'output_tokens' => 8, 'total_tokens' => 18],
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
        return ['provider' => $this->identifier()];
    }

    public function identifier(): string
    {
        return 'llm-platform-test';
    }
}

function createAnswerKnowledgeDocument(): KnowledgeDocumentRecord
{
    return KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'Sprint 22 Test Bible',
        'reference' => 'John 1:14 LLM',
        'title' => 'The Word became flesh',
        'content' => 'The Word became flesh and dwelt among us.',
    ]);
}

beforeEach(function (): void {
    config()->set('retrieval.profiles.ai_answer.use_vector', false);
    config()->set('retrieval.profiles.ai_answer.use_lexical', true);
    config()->set('retrieval.profiles.ai_answer.use_expansion', false);
    config()->set('retrieval.profiles.ai_answer.graph_depth', 0);
    config()->set('ai_security.enabled', true);
    config()->set('ai_security.external_processing.allow', false);
    config()->set('ai_security.pii.action', 'redact');
    config()->set('llm.default_provider', 'null');
    config()->set('llm.default_model', 'null-answer-model');
    config()->set('llm.routing.answer_generation', 'fast_local');
    config()->set('llm.profiles.fast_local.provider', 'null');
    config()->set('llm.profiles.fast_local.model', 'null-answer-model');
});

it('routes tasks to configured provider model profiles', function (): void {
    config()->set('llm.profiles.research.provider', 'openai');
    config()->set('llm.profiles.research.model', 'gpt-test');
    config()->set('llm.routing.answer_generation', 'research');

    $selection = app(LlmModelRouter::class)->select('answer_generation');

    expect($selection->provider)->toBe('openai')
        ->and($selection->model)->toBe('gpt-test')
        ->and($selection->profileName)->toBe('research');
});

it('gateway resolves configured providers and normalizes responses', function (): void {
    $provider = new LlmPlatformTestProvider();
    app()->instance(LLMProviderInterface::class, $provider);
    config()->set('llm.routing.answer_generation', 'fast_local');
    config()->set('llm.profiles.fast_local.provider', 'null');
    config()->set('llm.profiles.fast_local.model', 'null-answer-model');

    $gateway = app(LlmGateway::class)->complete('answer_generation', new LLMCompletionRequest(
        messages: [['role' => 'user', 'content' => 'Why did the Word become flesh?']],
        model: 'ignored-by-gateway',
    ));

    expect($gateway->completion->provider)->toBe('llm-platform-test')
        ->and($gateway->completion->model)->toBe('null-answer-model')
        ->and($gateway->selection->provider)->toBe('null')
        ->and($provider->lastRequest?->metadata['task'])->toBe('answer_generation');
});

it('gateway rejects unsupported required capabilities', function (): void {
    app()->instance(LLMProviderInterface::class, new LlmPlatformTestProvider());
    config()->set('llm.profiles.fast_local.requires_capabilities', ['tools']);
    config()->set('llm.models.null:null-answer-model.capabilities.tools', false);

    app(LlmGateway::class)->complete('answer_generation', new LLMCompletionRequest(
        messages: [['role' => 'user', 'content' => 'Use a tool']],
        model: 'null-answer-model',
    ));
})->throws(LlmProviderCapabilityException::class);

it('shows provider health without exposing secrets', function (): void {
    config()->set('llm.providers.openai.api_key', 'secret-test-key');

    $status = Artisan::call('ai:providers:health', ['--format' => 'json']);
    $payload = Artisan::output();

    expect($status)->toBe(0)
        ->and($payload)->toContain('"provider": "openai"')
        ->and($payload)->toContain('"status": "BLOCKED"')
        ->and($payload)->not->toContain('secret-test-key');
});

it('shows provider diagnostics with the requested command names', function (): void {
    $providersStatus = Artisan::call('ai:providers', ['--format' => 'json']);
    $providersPayload = Artisan::output();

    $healthStatus = Artisan::call('ai:llm-health', ['--format' => 'json']);
    $healthPayload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($providersStatus)->toBe(0)
        ->and($providersPayload)->toContain('"provider": "null"')
        ->and($healthStatus)->toBe(0)
        ->and($healthPayload['provider'])->toBe('null')
        ->and($healthPayload['model'])->toBe('null-answer-model')
        ->and($healthPayload['external_processing'])->toBeFalse();
});

it('keeps answer generation local and redacts pii before provider calls', function (): void {
    $provider = new LlmPlatformTestProvider();
    app()->instance(LLMProviderInterface::class, $provider);
    createAnswerKnowledgeDocument();

    $answer = app(AnswerQuestionService::class)->answer(
        question: 'Explain Word became flesh for user@example.com',
        filters: ['source_name' => 'Sprint 22 Test Bible'],
    );

    $payload = json_encode($provider->lastRequest?->messages, JSON_THROW_ON_ERROR);

    expect($answer->provider)->toBe('llm-platform-test')
        ->and($answer->metadata['llm_selection']['provider'])->toBe('null')
        ->and($answer->metadata['usage']['finish_reason'])->toBe('stop')
        ->and($answer->warnings)->toContain('PII_REDACTED')
        ->and($payload)->not->toContain('user@example.com')
        ->and($payload)->toContain('[REDACTED]');
});

it('blocks pii when configured before any provider call', function (): void {
    config()->set('ai_security.pii.action', 'block');
    app()->instance(LLMProviderInterface::class, new LlmPlatformTestProvider());
    createAnswerKnowledgeDocument();

    app(AnswerQuestionService::class)->answer(
        question: 'Explain Word became flesh for user@example.com',
        filters: ['source_name' => 'Sprint 22 Test Bible'],
    );
})->throws(AISecurityException::class);

it('saves model comparison evaluation runs using the existing evaluation engine', function (): void {
    EvaluationQuestionRecord::factory()->create([
        'question' => 'Provider safety smoke',
        'expected_references' => [],
        'expected_source_types' => [],
        'category' => 'safety',
        'difficulty' => 'easy',
    ]);

    $status = Artisan::call('ai:model:compare', [
        '--models' => 'null:null-answer-model,null:null-answer-model',
        '--type' => 'safety',
        '--limit' => 1,
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload['runs'])->toHaveCount(2)
        ->and(AiEvaluationRunRecord::query()->where('name', 'like', 'model-compare%')->count())->toBe(2);
});
