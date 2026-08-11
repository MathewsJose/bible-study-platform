<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\Exceptions\LlmAuthenticationException;
use App\Application\Knowledge\Answering\Exceptions\LlmProviderException;
use App\Application\Knowledge\Answering\Exceptions\LlmRateLimitException;
use App\Application\Knowledge\Answering\Exceptions\LlmTimeoutException;
use App\Infrastructure\Knowledge\AI\AnthropicProvider;
use App\Infrastructure\Knowledge\AI\GoogleProvider;
use App\Infrastructure\Knowledge\AI\LocalProvider;
use App\Infrastructure\Knowledge\AI\OpenAIProvider;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function llmRequest(string $model = 'test-model'): LLMCompletionRequest
{
    return new LLMCompletionRequest(
        messages: [
            ['role' => 'system', 'content' => 'Answer from context.'],
            ['role' => 'user', 'content' => 'Why did Jesus become man?'],
        ],
        model: $model,
        maxTokens: 100,
    );
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('llm.retry_attempts', 0);
    config()->set('llm.connect_timeout', 1);
    config()->set('llm.timeout', 2);
});

it('generates through an openai compatible local provider', function (): void {
    config()->set('llm.providers.local.base_url', 'http://local-llm.test');
    config()->set('llm.pricing.models.local:local-model', [
        'input_cost_per_1m_tokens' => 1,
        'output_cost_per_1m_tokens' => 2,
    ]);

    Http::fake([
        'http://local-llm.test/v1/chat/completions' => Http::response([
            'id' => 'local-response',
            'choices' => [['message' => ['content' => 'The Word became flesh.'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ]),
    ]);

    $response = app(LocalProvider::class)->complete(llmRequest('local-model'));

    expect($response->content)->toBe('The Word became flesh.')
        ->and($response->provider)->toBe('local')
        ->and($response->promptTokens)->toBe(10)
        ->and($response->completionTokens)->toBe(5)
        ->and($response->estimatedCost)->toBe(0.00002);
});

it('maps local provider connection failures to timeout exceptions', function (): void {
    config()->set('llm.providers.local.base_url', 'http://local-llm.test');
    Http::fake(['http://local-llm.test/*' => Http::failedConnection()]);

    app(LocalProvider::class)->complete(llmRequest('local-model'));
})->throws(LlmTimeoutException::class);

it('rejects malformed local provider responses', function (): void {
    config()->set('llm.providers.local.base_url', 'http://local-llm.test');
    Http::fake(['http://local-llm.test/v1/chat/completions' => Http::response(['choices' => []])]);

    app(LocalProvider::class)->complete(llmRequest('local-model'));
})->throws(LlmProviderException::class);

it('generates through openai and captures usage', function (): void {
    config()->set('llm.providers.openai.chat_url', 'https://api.openai.test/v1/chat/completions');
    config()->set('llm.providers.openai.api_key', 'test-key');

    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::response([
            'id' => 'openai-response',
            'choices' => [['message' => ['content' => 'Grounded answer.'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 7, 'total_tokens' => 18],
        ]),
    ]);

    $response = app(OpenAIProvider::class)->complete(llmRequest('gpt-test'));

    expect($response->content)->toBe('Grounded answer.')
        ->and($response->finishReason)->toBe('stop')
        ->and($response->totalTokens())->toBe(18);
});

it('maps openai provider failures', function (int $status, string $exceptionClass): void {
    config()->set('llm.providers.openai.chat_url', 'https://api.openai.test/v1/chat/completions');
    config()->set('llm.providers.openai.api_key', 'test-key');

    Http::fake([
        'https://api.openai.test/v1/chat/completions' => Http::response(['error' => ['message' => 'hidden']], $status),
    ]);

    try {
        app(OpenAIProvider::class)->complete(llmRequest('gpt-test'));
    } catch (\Throwable $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass);

        return;
    }

    expect('exception')->toBe('thrown');
})->with([
    'auth' => [401, LlmAuthenticationException::class],
    'rate limit' => [429, LlmRateLimitException::class],
    'server' => [500, LlmProviderException::class],
]);

it('generates through anthropic', function (): void {
    config()->set('llm.providers.anthropic.chat_url', 'https://api.anthropic.test/v1/messages');
    config()->set('llm.providers.anthropic.api_key', 'test-key');

    Http::fake([
        'https://api.anthropic.test/v1/messages' => Http::response([
            'id' => 'msg_123',
            'content' => [['type' => 'text', 'text' => 'Anthropic answer.']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 20, 'output_tokens' => 8],
        ]),
    ]);

    $response = app(AnthropicProvider::class)->complete(llmRequest('claude-test'));

    expect($response->content)->toBe('Anthropic answer.')
        ->and($response->provider)->toBe('anthropic')
        ->and($response->promptTokens)->toBe(20)
        ->and($response->completionTokens)->toBe(8);
});

it('rejects malformed anthropic responses', function (): void {
    config()->set('llm.providers.anthropic.chat_url', 'https://api.anthropic.test/v1/messages');
    config()->set('llm.providers.anthropic.api_key', 'test-key');
    Http::fake(['https://api.anthropic.test/v1/messages' => Http::response(['content' => []])]);

    app(AnthropicProvider::class)->complete(llmRequest('claude-test'));
})->throws(LlmProviderException::class);

it('generates through google', function (): void {
    config()->set('llm.providers.google.base_url', 'https://generativelanguage.test');
    config()->set('llm.providers.google.api_key', 'test-key');

    Http::fake([
        'https://generativelanguage.test/*' => Http::response([
            'responseId' => 'google-response',
            'candidates' => [['content' => ['parts' => [['text' => 'Google answer.']]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 14, 'candidatesTokenCount' => 6, 'totalTokenCount' => 20],
        ]),
    ]);

    $response = app(GoogleProvider::class)->complete(llmRequest('gemini-test'));

    expect($response->content)->toBe('Google answer.')
        ->and($response->provider)->toBe('google')
        ->and($response->promptTokens)->toBe(14);
});

it('maps google authentication failures', function (): void {
    config()->set('llm.providers.google.base_url', 'https://generativelanguage.test');
    config()->set('llm.providers.google.api_key', 'test-key');
    Http::fake(['https://generativelanguage.test/*' => Http::response(['error' => ['message' => 'nope']], 403)]);

    app(GoogleProvider::class)->complete(llmRequest('gemini-test'));
})->throws(LlmAuthenticationException::class);
