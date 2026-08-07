<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\AI;

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $started = microtime(true);
        $providerKey = $this->providerKey();
        $response = Http::withToken((string) config("ai.providers.{$providerKey}.api_key"))
            ->timeout((int) config('ai.timeout', 30))
            ->retry((int) config('ai.retry_attempts', 2), (int) config('ai.retry_sleep_ms', 250))
            ->post((string) config("ai.providers.{$providerKey}.url"), [
                'model' => $request->model,
                'messages' => $request->messages,
                'temperature' => $request->temperature,
                'max_tokens' => $request->maxTokens,
            ])
            ->throw()
            ->json();

        return new LLMCompletionResponse(
            content: (string) data_get($response, 'choices.0.message.content', ''),
            provider: $this->identifier(),
            model: $request->model,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            promptTokens: data_get($response, 'usage.prompt_tokens'),
            completionTokens: data_get($response, 'usage.completion_tokens'),
            metadata: ['id' => data_get($response, 'id')],
        );
    }

    public function stream(LLMCompletionRequest $request): iterable
    {
        yield $this->complete($request)->content;
    }

    public function countTokens(string $text): ?int
    {
        return max(1, (int) ceil(str_word_count($text) * 1.35));
    }

    public function metadata(): array
    {
        return ['provider' => $this->identifier(), 'streaming' => false, 'token_counting' => 'estimated'];
    }

    public function identifier(): string
    {
        return 'openai';
    }

    protected function providerKey(): string
    {
        return 'openai';
    }
}
