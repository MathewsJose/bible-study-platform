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

readonly class AnthropicProvider implements LLMProviderInterface
{
    use MapsLlmProviderErrors;

    public function __construct(private LlmUsageCostCalculator $costs) {}

    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $started = microtime(true);
        $url = (string) config('llm.providers.anthropic.chat_url', '');
        $apiKey = (string) config('llm.providers.anthropic.api_key', '');
        $this->ensureConfigured($this->identifier(), $url, $apiKey, requiresApiKey: true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => (string) config('llm.providers.anthropic.version', '2023-06-01'),
            ])
                ->timeout((int) config('llm.timeout', 30))
                ->connectTimeout((int) config('llm.connect_timeout', 5))
                ->retry((int) config('llm.retry_attempts', 2), (int) config('llm.retry_sleep_ms', 250), $this->shouldRetry(...))
                ->post($url, [
                    'model' => $request->model,
                    'system' => $this->systemPrompt($request),
                    'messages' => $this->messages($request),
                    'temperature' => $request->temperature,
                    'max_tokens' => $request->maxTokens,
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->mapProviderException($exception, $this->identifier(), $request->model);
        }

        $inputTokens = data_get($response, 'usage.input_tokens');
        $outputTokens = data_get($response, 'usage.output_tokens');

        $content = data_get($response, 'content.0.text');
        if (! is_string($content) || trim($content) === '') {
            throw new LlmProviderException('LLM provider returned a malformed response.', $this->identifier(), $request->model);
        }

        return new LLMCompletionResponse(
            content: $content,
            provider: $this->identifier(),
            model: $request->model,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
            metadata: ['id' => data_get($response, 'id')],
            finishReason: data_get($response, 'stop_reason'),
            usage: [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => is_numeric($inputTokens) && is_numeric($outputTokens) ? (int) $inputTokens + (int) $outputTokens : null,
            ],
            estimatedCost: $this->costs->estimate(
                $this->identifier(),
                $request->model,
                is_numeric($inputTokens) ? (int) $inputTokens : null,
                is_numeric($outputTokens) ? (int) $outputTokens : null,
            ),
        );
    }

    public function stream(LLMCompletionRequest $request): iterable
    {
        yield $this->complete($request)->content;
    }

    public function countTokens(string $text): int
    {
        return max(1, (int) ceil(str_word_count($text) * 1.35));
    }

    public function metadata(): array
    {
        return ['provider' => $this->identifier(), 'streaming' => false, 'token_counting' => 'estimated'];
    }

    public function identifier(): string
    {
        return 'anthropic';
    }

    private function systemPrompt(LLMCompletionRequest $request): ?string
    {
        foreach ($request->messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                return $message['content'];
            }
        }

        return null;
    }

    /** @return list<array{role: string, content: string}> */
    private function messages(LLMCompletionRequest $request): array
    {
        return array_values(array_filter(
            $request->messages,
            static fn (array $message): bool => ($message['role'] ?? '') !== 'system',
        ));
    }

    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError()));
    }
}
