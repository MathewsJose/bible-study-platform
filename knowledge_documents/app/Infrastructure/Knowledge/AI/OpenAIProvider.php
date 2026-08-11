<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\AI;

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Answering\Exceptions\LlmProviderException;
use App\Application\Knowledge\Answering\Services\LlmUsageCostCalculator;
use App\Infrastructure\Knowledge\AI\Concerns\MapsLlmProviderErrors;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAIProvider implements LLMProviderInterface
{
    use MapsLlmProviderErrors;

    public function __construct(private readonly LlmUsageCostCalculator $costs) {}

    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $started = microtime(true);
        $providerKey = $this->providerKey();
        $url = (string) (config("llm.providers.{$providerKey}.chat_url") ?: config("ai.providers.{$providerKey}.url"));
        $apiKey = (string) (config("llm.providers.{$providerKey}.api_key") ?: config("ai.providers.{$providerKey}.api_key"));
        $this->ensureConfigured($this->identifier(), $url, $apiKey, requiresApiKey: true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('llm.timeout', config('ai.timeout', 30)))
                ->connectTimeout((int) config('llm.connect_timeout', 5))
                ->retry((int) config('llm.retry_attempts', 2), (int) config('llm.retry_sleep_ms', 250), $this->shouldRetry(...))
                ->post($url, [
                    'model' => $request->model,
                    'messages' => $request->messages,
                    'temperature' => $request->temperature,
                    'max_tokens' => $request->maxTokens,
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->mapProviderException($exception, $this->identifier(), $request->model);
        }

        $content = data_get($response, 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new LlmProviderException('LLM provider returned a malformed response.', $this->identifier(), $request->model);
        }

        return new LLMCompletionResponse(
            content: $content,
            provider: $this->identifier(),
            model: $request->model,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            promptTokens: data_get($response, 'usage.prompt_tokens'),
            completionTokens: data_get($response, 'usage.completion_tokens'),
            metadata: ['id' => data_get($response, 'id')],
            finishReason: data_get($response, 'choices.0.finish_reason'),
            usage: [
                'input_tokens' => data_get($response, 'usage.prompt_tokens'),
                'output_tokens' => data_get($response, 'usage.completion_tokens'),
                'total_tokens' => data_get($response, 'usage.total_tokens'),
            ],
            estimatedCost: $this->costs->estimate(
                $this->identifier(),
                $request->model,
                is_numeric(data_get($response, 'usage.prompt_tokens')) ? (int) data_get($response, 'usage.prompt_tokens') : null,
                is_numeric(data_get($response, 'usage.completion_tokens')) ? (int) data_get($response, 'usage.completion_tokens') : null,
            ),
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

    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError()));
    }
}
