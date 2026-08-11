<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\DTOs;

final readonly class AnswerEvaluation
{
    /**
     * @param  list<string>  $unsupportedClaims
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $citationDetails
     * @param  array<string, mixed>  $sourceCoverage
     */
    public function __construct(
        public string $groundedness,
        public float $citationCorrectness,
        public float $citationCompleteness,
        public float $sourceCoverageScore,
        public float $answerCompleteness,
        public array $unsupportedClaims,
        public array $warnings,
        public array $citationDetails,
        public array $sourceCoverage,
    ) {}

    public function score(): float
    {
        $groundednessScore = match ($this->groundedness) {
            'supported' => 1.0,
            'partially_supported' => 0.6,
            default => 0.0,
        };

        return round(($groundednessScore + $this->citationCorrectness + $this->citationCompleteness + $this->sourceCoverageScore + $this->answerCompleteness) / 5, 6);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'groundedness' => $this->groundedness,
            'citation_correctness' => $this->citationCorrectness,
            'citation_completeness' => $this->citationCompleteness,
            'source_coverage' => $this->sourceCoverageScore,
            'answer_completeness' => $this->answerCompleteness,
            'score' => $this->score(),
            'unsupported_claims' => $this->unsupportedClaims,
            'warnings' => $this->warnings,
            'citation_details' => $this->citationDetails,
            'source_coverage_details' => $this->sourceCoverage,
        ];
    }
}
