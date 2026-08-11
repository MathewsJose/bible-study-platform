<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\AnswerData;
use App\Application\Knowledge\Answering\DTOs\AnswerDiagnostics;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;
use App\Application\Knowledge\Answering\Exceptions\LlmPolicyDeniedException;
use App\Application\Knowledge\Answering\Exceptions\LlmProviderException;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use App\Application\Knowledge\Security\Contracts\AISecurityPolicyInterface;
use App\Application\Knowledge\Security\Exceptions\AISecurityException;
use Throwable;

final readonly class AnswerQuestionService
{
    public function __construct(
        private RetrievalEngine $retrieval,
        private CitationBuilder $citations,
        private PromptBuilder $prompts,
        private LLMProviderInterface $provider,
        private LlmModelRouter $models,
        private LlmProviderRegistry $providers,
        private ResponseValidator $validator,
        private ConfidenceScorer $confidence,
        private AISecurityPolicyInterface $security,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array{role: string, content: string}>  $history
     */
    public function answer(
        string $question,
        ?string $profile = 'ai_answer',
        array $filters = [],
        array $history = [],
    ): AnswerData {
        $started = microtime(true);
        $timings = [];
        $security = $this->security->evaluateInput($question, ['surface' => 'answer']);

        if (! $security->allowed) {
            throw new AISecurityException($security->errorCode, $security->message);
        }

        $safeQuestion = $security->safeInput;
        $safeHistory = $this->safeHistory($history);

        $stage = microtime(true);
        $retrieval = $this->retrieval->retrieve($safeQuestion, $profile, $filters, includeExplanations: true);
        $timings['retrieval'] = $this->elapsedMs($stage);

        $stage = microtime(true);
        $citations = $this->citations->build($retrieval);
        $prompt = $this->prompts->build($safeQuestion, $retrieval, $citations, $safeHistory);
        $timings['prompt_builder'] = $this->elapsedMs($stage);

        $stage = microtime(true);
        $selection = $this->models->select('answer_generation');
        $provider = $this->providerForSelection($selection->provider);
        $model = $this->modelForSelection($selection->model, $provider);
        try {
            $completion = $this->completeWithPolicy($provider, $model, $prompt->messages, $selection->profileName, $selection->task);
            $providerWarnings = [];
        } catch (LlmProviderException $exception) {
            $completion = $this->fallbackCompletion($exception, $selection->fallbackProfile, $prompt->messages, $stage);
            $providerWarnings = ['Provider failed safely.'];
        } catch (AISecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $completion = new LLMCompletionResponse(
                content: (string) config('ai.guardrails.insufficient_evidence_message'),
                provider: $provider->identifier(),
                model: $model,
                latencyMs: $this->elapsedMs($stage),
                metadata: ['exception' => 'Provider request failed.'],
            );
            $providerWarnings = ['Provider failed safely.'];
        }
        $timings['llm_provider'] = $this->elapsedMs($stage);

        $stage = microtime(true);
        $validation = $this->validator->validate($completion->content, $citations);
        $confidence = $this->confidence->score($retrieval, $citations);
        $timings['validation_confidence'] = $this->elapsedMs($stage);
        $timings['total'] = $this->elapsedMs($started);

        return new AnswerData(
            question: $safeQuestion,
            answer: $completion->content,
            supportingDocuments: $retrieval->context,
            citations: $citations,
            confidence: $confidence,
            provider: $completion->provider,
            model: $completion->model,
            latencyMs: $completion->latencyMs,
            promptTokens: $completion->promptTokens ?? $prompt->estimatedTokens,
            completionTokens: $completion->completionTokens,
            warnings: array_values(array_unique([...$providerWarnings, ...$security->warnings, ...$validation->warnings])),
            metadata: [
                'retrieval_profile' => $retrieval->profile->identifier,
                'llm_selection' => $selection->toArray(),
                'provider_metadata' => $completion->metadata,
                'usage' => [
                    'input_tokens' => $completion->promptTokens ?? $prompt->estimatedTokens,
                    'output_tokens' => $completion->completionTokens,
                    'total_tokens' => $completion->totalTokens(),
                    'estimated_cost' => $completion->estimatedCost,
                    'finish_reason' => $completion->finishReason,
                ],
                'prompt_diagnostics' => $prompt->diagnostics,
                'security' => $security->diagnostics(),
            ],
            diagnostics: new AnswerDiagnostics($timings, [
                'prompt_tokens' => $completion->promptTokens ?? $prompt->estimatedTokens,
                'completion_tokens' => $completion->completionTokens,
                'estimated_cost' => $completion->estimatedCost,
                'citations' => count($citations),
                'confidence' => $confidence->score,
                'provider_latency_ms' => $completion->latencyMs,
            ]),
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function completeWithPolicy(LLMProviderInterface $provider, string $model, array $messages, string $profile, string $task): LLMCompletionResponse
    {
        $providerSecurity = $this->security->evaluateProvider($provider->identifier(), $messages, ['surface' => 'answer']);

        if (! $providerSecurity->allowed) {
            throw new LlmPolicyDeniedException($providerSecurity->message, $provider->identifier(), $model, ['error_code' => $providerSecurity->errorCode]);
        }

        return $provider->complete(new LLMCompletionRequest(
            messages: $messages,
            model: $model,
            temperature: (float) config('ai.temperature', 0.0),
            maxTokens: min((int) config('ai.max_tokens', 800), (int) config('ai_security.limits.max_output_tokens', 1200)),
            metadata: ['profile' => $profile, 'task' => $task],
        ));
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function fallbackCompletion(LlmProviderException $exception, ?string $fallbackProfile, array $messages, float $started): LLMCompletionResponse
    {
        if ($exception instanceof LlmPolicyDeniedException || $fallbackProfile === null || $fallbackProfile === '') {
            return new LLMCompletionResponse(
                content: (string) config('ai.guardrails.insufficient_evidence_message'),
                provider: $exception->provider,
                model: $exception->model ?? (string) config('llm.default_model', config('ai.model', 'null-answer-model')),
                latencyMs: $this->elapsedMs($started),
                metadata: ['exception' => 'Provider request failed.'],
            );
        }

        $fallback = $this->models->select('answer_generation', $fallbackProfile);
        $provider = $this->providers->provider($fallback->provider);

        try {
            return $this->completeWithPolicy($provider, $fallback->model, $messages, $fallback->profileName, $fallback->task);
        } catch (Throwable) {
            return new LLMCompletionResponse(
                content: (string) config('ai.guardrails.insufficient_evidence_message'),
                provider: $provider->identifier(),
                model: $fallback->model,
                latencyMs: $this->elapsedMs($started),
                metadata: ['exception' => 'Provider fallback failed.'],
            );
        }
    }

    private function providerForSelection(string $provider): LLMProviderInterface
    {
        $currentProvider = $this->provider->identifier();

        if (! in_array($currentProvider, ['null', 'local', 'ollama', 'openai', 'anthropic', 'claude', 'google', 'gemini'], true)) {
            return $this->provider;
        }

        return $this->providers->provider($provider);
    }

    private function modelForSelection(string $model, LLMProviderInterface $provider): string
    {
        if (! in_array($provider->identifier(), ['null', 'local', 'ollama', 'openai', 'anthropic', 'google'], true)) {
            return (string) config('ai.model', $model);
        }

        return $model;
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return list<array{role: string, content: string}>
     */
    private function safeHistory(array $history): array
    {
        $safe = [];

        foreach ($history as $message) {
            $evaluation = $this->security->evaluateInput($message['content'], ['surface' => 'answer_history']);

            if (! $evaluation->allowed) {
                throw new AISecurityException($evaluation->errorCode, $evaluation->message);
            }

            $safe[] = [
                'role' => $message['role'],
                'content' => $evaluation->safeInput,
            ];
        }

        return $safe;
    }
}
