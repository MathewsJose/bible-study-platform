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

final readonly class OllamaProvider implements LLMProviderInterface
{
    use MapsLlmProviderErrors;

    public function __construct(private LlmUsageCostCalculator $costs) {}

    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $started = microtime(true);
        $url = (string) (config('llm.providers.ollama.chat_url') ?: config('ai.providers.ollama.url'));
        $this->ensureConfigured($this->identifier(), $url);

        try {
            $response = Http::timeout((int) config('llm.timeout', config('ai.timeout', 30)))
                ->connectTimeout((int) config('llm.connect_timeout', 5))
                ->retry((int) config('llm.retry_attempts', 2), (int) config('llm.retry_sleep_ms', 250), $this->shouldRetry(...))
                ->post($url, [
                    'model' => $request->model,
                    'messages' => $request->messages,
                    'stream' => false,
                    'options' => ['temperature' => $request->temperature],
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->mapProviderException($exception, $this->identifier(), $request->model);
        }

        $content = data_get($response, 'message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new LlmProviderException('LLM provider returned a malformed response.', $this->identifier(), $request->model);
        }

        return new LLMCompletionResponse(
            content: $content,
            provider: $this->identifier(),
            model: $request->model,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            promptTokens: data_get($response, 'prompt_eval_count'),
            completionTokens: data_get($response, 'eval_count'),
            metadata: ['done' => data_get($response, 'done')],
            finishReason: data_get($response, 'done_reason'),
            usage: [
                'input_tokens' => data_get($response, 'prompt_eval_count'),
                'output_tokens' => data_get($response, 'eval_count'),
                'total_tokens' => is_numeric(data_get($response, 'prompt_eval_count')) && is_numeric(data_get($response, 'eval_count'))
                    ? (int) data_get($response, 'prompt_eval_count') + (int) data_get($response, 'eval_count')
                    : null,
            ],
            estimatedCost: $this->costs->estimate(
                $this->identifier(),
                $request->model,
                is_numeric(data_get($response, 'prompt_eval_count')) ? (int) data_get($response, 'prompt_eval_count') : null,
                is_numeric(data_get($response, 'eval_count')) ? (int) data_get($response, 'eval_count') : null,
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
        return 'ollama';
    }

    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError()));
    }
}
