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

readonly class GoogleProvider implements LLMProviderInterface
{
    use MapsLlmProviderErrors;

    public function __construct(private LlmUsageCostCalculator $costs) {}

    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $started = microtime(true);
        $baseUrl = rtrim((string) config('llm.providers.google.base_url', ''), '/');
        $apiKey = (string) config('llm.providers.google.api_key', '');
        $this->ensureConfigured($this->identifier(), $baseUrl, $apiKey, requiresApiKey: true);

        try {
            $response = Http::timeout((int) config('llm.timeout', 30))
                ->connectTimeout((int) config('llm.connect_timeout', 5))
                ->retry((int) config('llm.retry_attempts', 2), (int) config('llm.retry_sleep_ms', 250), $this->shouldRetry(...))
                ->post($baseUrl.'/v1beta/models/'.$request->model.':generateContent?key='.$apiKey, [
                    'systemInstruction' => $this->systemInstruction($request),
                    'contents' => $this->contents($request),
                    'generationConfig' => [
                        'temperature' => $request->temperature,
                        'maxOutputTokens' => $request->maxTokens,
                    ],
                ])
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            throw $this->mapProviderException($exception, $this->identifier(), $request->model);
        }

        $inputTokens = data_get($response, 'usageMetadata.promptTokenCount');
        $outputTokens = data_get($response, 'usageMetadata.candidatesTokenCount');
        $totalTokens = data_get($response, 'usageMetadata.totalTokenCount');

        $content = data_get($response, 'candidates.0.content.parts.0.text');
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
            metadata: ['response_id' => data_get($response, 'responseId')],
            finishReason: data_get($response, 'candidates.0.finishReason'),
            usage: [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
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
        return 'google';
    }

    /** @return array{parts: list<array{text: string}>}|null */
    private function systemInstruction(LLMCompletionRequest $request): ?array
    {
        foreach ($request->messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                return ['parts' => [['text' => $message['content']]]];
            }
        }

        return null;
    }

    /** @return list<array{role: string, parts: list<array{text: string}>}> */
    private function contents(LLMCompletionRequest $request): array
    {
        return array_values(array_map(
            static fn (array $message): array => [
                'role' => ($message['role'] ?? '') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ],
            array_filter($request->messages, static fn (array $message): bool => ($message['role'] ?? '') !== 'system'),
        ));
    }

    private function shouldRetry(Throwable $exception, PendingRequest $request): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError()));
    }
}
