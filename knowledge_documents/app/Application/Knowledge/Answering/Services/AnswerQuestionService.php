<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\AnswerData;
use App\Application\Knowledge\Answering\DTOs\AnswerDiagnostics;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
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
        try {
            $providerSecurity = $this->security->evaluateProvider($this->provider->identifier(), $prompt->messages, ['surface' => 'answer']);

            if (! $providerSecurity->allowed) {
                throw new AISecurityException($providerSecurity->errorCode, $providerSecurity->message);
            }

            $completion = $this->provider->complete(new LLMCompletionRequest(
                messages: $prompt->messages,
                model: (string) config('ai.model', 'null-answer-model'),
                temperature: (float) config('ai.temperature', 0.0),
                maxTokens: min((int) config('ai.max_tokens', 800), (int) config('ai_security.limits.max_output_tokens', 1200)),
            ));
            $providerWarnings = [];
        } catch (AISecurityException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $completion = new \App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse(
                content: (string) config('ai.guardrails.insufficient_evidence_message'),
                provider: $this->provider->identifier(),
                model: (string) config('ai.model', 'null-answer-model'),
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
                'provider_metadata' => $completion->metadata,
                'prompt_diagnostics' => $prompt->diagnostics,
                'security' => $security->diagnostics(),
            ],
            diagnostics: new AnswerDiagnostics($timings, [
                'prompt_tokens' => $completion->promptTokens ?? $prompt->estimatedTokens,
                'completion_tokens' => $completion->completionTokens,
                'citations' => count($citations),
                'confidence' => $confidence->score,
                'provider_latency_ms' => $completion->latencyMs,
            ]),
        );
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
