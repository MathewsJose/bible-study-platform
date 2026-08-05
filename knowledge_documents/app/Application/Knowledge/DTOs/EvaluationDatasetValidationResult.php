<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class EvaluationDatasetValidationResult
{
    /**
     * @param  list<array{question_id: string, question: string, references: list<string>}>  $missingReferences
     * @param  list<array{question_id: string, question: string, source_types: list<string>}>  $invalidSourceTypes
     * @param  list<array{question_id: string, question: string, references: list<string>}>  $duplicateExpectedReferences
     * @param  list<array{question_id: string, question: string}>  $questionsWithoutExpectedReferences
     */
    public function __construct(
        public int $totalQuestions,
        public int $validQuestions,
        public int $invalidQuestions,
        public array $missingReferences,
        public array $invalidSourceTypes,
        public array $duplicateExpectedReferences,
        public array $questionsWithoutExpectedReferences,
    ) {}

    public function isValid(): bool
    {
        return $this->invalidQuestions === 0;
    }
}
