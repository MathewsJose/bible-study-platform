<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

use App\Application\Knowledge\Retrieval\DTOs\RetrievalContextDocument;

final readonly class AnswerData
{
    /**
     * @param  list<RetrievalContextDocument>  $supportingDocuments
     * @param  list<CitationData>  $citations
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $question,
        public string $answer,
        public array $supportingDocuments,
        public array $citations,
        public ConfidenceData $confidence,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public array $warnings,
        public array $metadata,
        public AnswerDiagnostics $diagnostics,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'question' => $this->question,
            'answer' => $this->answer,
            'supporting_documents' => array_map(
                static fn (RetrievalContextDocument $document): array => $document->toArray(true),
                $this->supportingDocuments,
            ),
            'citations' => array_map(static fn (CitationData $citation): array => $citation->toArray(), $this->citations),
            'confidence' => $this->confidence->toArray(),
            'provider' => $this->provider,
            'model' => $this->model,
            'latency_ms' => $this->latencyMs,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
            'diagnostics' => $this->diagnostics->toArray(),
        ];
    }
}
