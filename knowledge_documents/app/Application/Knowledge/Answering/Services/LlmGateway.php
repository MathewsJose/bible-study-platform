<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\Contracts\LLMGatewayInterface;
use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Answering\DTOs\LlmGatewayResponse;
use App\Application\Knowledge\Answering\DTOs\LlmModelSelection;
use App\Application\Knowledge\Answering\Exceptions\LlmPolicyDeniedException;
use App\Application\Knowledge\Answering\Exceptions\LlmProviderCapabilityException;
use App\Application\Knowledge\Answering\Exceptions\LlmProviderException;
use App\Application\Knowledge\Security\Contracts\AISecurityPolicyInterface;
use Throwable;

final readonly class LlmGateway implements LLMGatewayInterface
{
    /**
     * @var list<string>
     */
    private const CONFIGURED_PROVIDER_IDENTIFIERS = ['null', 'local', 'ollama', 'openai', 'anthropic', 'claude', 'google', 'gemini'];

    public function __construct(
        private LlmModelRouter $models,
        private LlmProviderRegistry $providers,
        private LLMProviderInterface $defaultProvider,
        private AISecurityPolicyInterface $security,
    ) {}

    public function complete(string $task, LLMCompletionRequest $request, ?string $profile = null): LlmGatewayResponse
    {
        $started = microtime(true);
        $selection = $this->models->select($task, $profile);
        $provider = $this->providerForSelection($selection->provider);
        $model = $this->modelForSelection($selection->model, $provider);

        try {
            return new LlmGatewayResponse(
                completion: $this->completeWithPolicy($provider, $model, $selection, $request),
                selection: $selection,
            );
        } catch (LlmProviderCapabilityException $exception) {
            throw $exception;
        } catch (LlmProviderException $exception) {
            return $this->fallbackCompletion($exception, $selection, $request, $started);
        } catch (Throwable) {
            return new LlmGatewayResponse(
                completion: $this->safeFailureCompletion($provider->identifier(), $model, $started, 'Provider request failed.'),
                selection: $selection,
                usedFallback: true,
                warning: 'Provider failed safely.',
            );
        }
    }

    private function completeWithPolicy(LLMProviderInterface $provider, string $model, LlmModelSelection $selection, LLMCompletionRequest $request): LLMCompletionResponse
    {
        $this->ensureCapabilities($selection);

        $providerSecurity = $this->security->evaluateProvider($provider->identifier(), $request->messages, ['surface' => 'llm_gateway', 'task' => $selection->task]);

        if (! $providerSecurity->allowed) {
            throw new LlmPolicyDeniedException($providerSecurity->message, $provider->identifier(), $model, ['error_code' => $providerSecurity->errorCode]);
        }

        return $provider->complete(new LLMCompletionRequest(
            messages: $request->messages,
            model: $model,
            temperature: $request->temperature,
            maxTokens: $request->maxTokens,
            options: $request->options,
            metadata: [
                ...$request->metadata,
                'profile' => $selection->profileName,
                'task' => $selection->task,
            ],
        ));
    }

    private function fallbackCompletion(LlmProviderException $exception, LlmModelSelection $selection, LLMCompletionRequest $request, float $started): LlmGatewayResponse
    {
        if ($exception instanceof LlmPolicyDeniedException || $selection->fallbackProfile === null || $selection->fallbackProfile === '') {
            return new LlmGatewayResponse(
                completion: $this->safeFailureCompletion(
                    provider: $exception->provider,
                    model: $exception->model ?? (string) config('llm.default_model', config('ai.model', 'null-answer-model')),
                    started: $started,
                    reason: 'Provider request failed.',
                ),
                selection: $selection,
                usedFallback: true,
                warning: 'Provider failed safely.',
            );
        }

        $fallback = $this->models->select($selection->task, $selection->fallbackProfile);
        $provider = $this->providers->provider($fallback->provider);

        try {
            return new LlmGatewayResponse(
                completion: $this->completeWithPolicy($provider, $fallback->model, $fallback, $request),
                selection: $fallback,
                usedFallback: true,
                warning: 'Provider failed safely.',
            );
        } catch (Throwable) {
            return new LlmGatewayResponse(
                completion: $this->safeFailureCompletion($provider->identifier(), $fallback->model, $started, 'Provider fallback failed.'),
                selection: $fallback,
                usedFallback: true,
                warning: 'Provider failed safely.',
            );
        }
    }

    private function safeFailureCompletion(string $provider, string $model, float $started, string $reason): LLMCompletionResponse
    {
        return new LLMCompletionResponse(
            content: (string) config('ai.guardrails.insufficient_evidence_message'),
            provider: $provider,
            model: $model,
            latencyMs: $this->elapsedMs($started),
            metadata: ['exception' => $reason],
        );
    }

    private function providerForSelection(string $provider): LLMProviderInterface
    {
        if (! in_array($this->defaultProvider->identifier(), self::CONFIGURED_PROVIDER_IDENTIFIERS, true)) {
            return $this->defaultProvider;
        }

        return $this->providers->provider($provider);
    }

    private function modelForSelection(string $model, LLMProviderInterface $provider): string
    {
        if (! in_array($provider->identifier(), self::CONFIGURED_PROVIDER_IDENTIFIERS, true)) {
            return (string) config('ai.model', $model);
        }

        return $model;
    }

    private function ensureCapabilities(LlmModelSelection $selection): void
    {
        $required = $selection->profile['requires_capabilities'] ?? [];

        if (! is_array($required)) {
            return;
        }

        foreach ($required as $capability) {
            if (! is_string($capability)) {
                continue;
            }

            if (($selection->capabilities[$capability] ?? null) !== true) {
                throw new LlmProviderCapabilityException(
                    message: "LLM model does not support required capability [{$capability}].",
                    provider: $selection->provider,
                    model: $selection->model,
                    diagnostics: ['capability' => $capability, 'profile' => $selection->profileName],
                );
            }
        }
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
