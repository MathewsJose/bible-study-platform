<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;

final readonly class RetrievalEvaluationResult
{
    /**
     * @param  list<string>  $expectedReferences
     * @param  list<array<string, mixed>>  $retrievedResults
     */
    public function __construct(
        public EvaluationQuestionRecord $question,
        public array $expectedReferences,
        public array $retrievedResults,
        public bool $hit,
        public float $precision,
        public float $recall,
        public float $reciprocalRank,
        public float $ndcg,
        public float $sourceCoverage,
        public array $sourceCoverageDetails,
        public int $executionTimeMs,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'question_id' => $this->question->id,
            'question' => $this->question->question,
            'expected_references' => $this->expectedReferences,
            'retrieved_results' => $this->retrievedResults,
            'hit' => $this->hit,
            'precision' => $this->precision,
            'recall' => $this->recall,
            'reciprocal_rank' => $this->reciprocalRank,
            'ndcg' => $this->ndcg,
            'source_coverage' => $this->sourceCoverage,
            'source_coverage_details' => $this->sourceCoverageDetails,
            'execution_time_ms' => $this->executionTimeMs,
        ];
    }
}
