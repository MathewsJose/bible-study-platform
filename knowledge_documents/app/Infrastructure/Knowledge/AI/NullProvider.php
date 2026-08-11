<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\AI;

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;

final readonly class NullProvider implements LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse
    {
        $started = microtime(true);

        return new LLMCompletionResponse(
            content: (string) config('ai.guardrails.insufficient_evidence_message'),
            provider: $this->identifier(),
            model: $request->model,
            latencyMs: (int) round((microtime(true) - $started) * 1000),
            promptTokens: $this->countTokens(json_encode($request->messages, JSON_THROW_ON_ERROR)),
            completionTokens: $this->countTokens((string) config('ai.guardrails.insufficient_evidence_message')),
            metadata: ['offline' => true],
            finishReason: 'deterministic_fallback',
            usage: [
                'input_tokens' => $this->countTokens(json_encode($request->messages, JSON_THROW_ON_ERROR)),
                'output_tokens' => $this->countTokens((string) config('ai.guardrails.insufficient_evidence_message')),
                'total_tokens' => $this->countTokens(json_encode($request->messages, JSON_THROW_ON_ERROR)) + $this->countTokens((string) config('ai.guardrails.insufficient_evidence_message')),
            ],
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
        return ['provider' => $this->identifier(), 'streaming' => true, 'token_counting' => 'estimated'];
    }

    public function identifier(): string
    {
        return 'null';
    }
}
