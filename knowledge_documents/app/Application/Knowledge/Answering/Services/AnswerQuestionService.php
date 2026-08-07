<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\DTOs\AnswerData;
use App\Application\Knowledge\Answering\DTOs\AnswerDiagnostics;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
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

        $stage = microtime(true);
        $retrieval = $this->retrieval->retrieve($question, $profile, $filters, includeExplanations: true);
        $timings['retrieval'] = $this->elapsedMs($stage);

        $stage = microtime(true);
        $citations = $this->citations->build($retrieval);
        $prompt = $this->prompts->build($question, $retrieval, $citations, $history);
        $timings['prompt_builder'] = $this->elapsedMs($stage);

        $stage = microtime(true);
        try {
            $completion = $this->provider->complete(new LLMCompletionRequest(
                messages: $prompt->messages,
                model: (string) config('ai.model', 'null-answer-model'),
                temperature: (float) config('ai.temperature', 0.0),
                maxTokens: (int) config('ai.max_tokens', 800),
            ));
            $providerWarnings = [];
        } catch (Throwable $exception) {
            $completion = new \App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse(
                content: (string) config('ai.guardrails.insufficient_evidence_message'),
                provider: $this->provider->identifier(),
                model: (string) config('ai.model', 'null-answer-model'),
                latencyMs: $this->elapsedMs($stage),
                metadata: ['exception' => $exception->getMessage()],
            );
            $providerWarnings = ['Provider failed: '.$exception->getMessage()];
        }
        $timings['llm_provider'] = $this->elapsedMs($stage);

        $stage = microtime(true);
        $validation = $this->validator->validate($completion->content, $citations);
        $confidence = $this->confidence->score($retrieval, $citations);
        $timings['validation_confidence'] = $this->elapsedMs($stage);
        $timings['total'] = $this->elapsedMs($started);

        return new AnswerData(
            question: $question,
            answer: $completion->content,
            supportingDocuments: $retrieval->context,
            citations: $citations,
            confidence: $confidence,
            provider: $completion->provider,
            model: $completion->model,
            latencyMs: $completion->latencyMs,
            promptTokens: $completion->promptTokens ?? $prompt->estimatedTokens,
            completionTokens: $completion->completionTokens,
            warnings: array_values(array_unique([...$providerWarnings, ...$validation->warnings])),
            metadata: [
                'retrieval_profile' => $retrieval->profile->identifier,
                'provider_metadata' => $completion->metadata,
                'prompt_diagnostics' => $prompt->diagnostics,
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
}
