<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\Services;

use App\Application\Knowledge\Answering\DTOs\AnswerData;
use App\Application\Knowledge\Answering\DTOs\CitationData;
use App\Application\Knowledge\Evaluation\Contracts\AnswerEvaluatorInterface;
use App\Application\Knowledge\Evaluation\DTOs\AnswerEvaluation;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalContextDocument;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Support\Str;

final readonly class DeterministicAnswerEvaluator implements AnswerEvaluatorInterface
{
    public function evaluate(AnswerData $answer, EvaluationQuestionRecord $question): AnswerEvaluation
    {
        $availableReferences = array_map(
            static fn (RetrievalContextDocument $document): string => $document->candidate->document->reference,
            $answer->supportingDocuments,
        );
        $citedReferences = array_map(static fn (CitationData $citation): string => $citation->reference, $answer->citations);
        $requiredCitations = $this->stringList($question->required_citations ?? $question->expected_references ?? []);
        $expectedSourceTypes = $this->stringList($question->expected_source_types ?? []);
        $expectedFacts = $this->stringList($question->expected_answer_facts ?? []);

        $citationDetails = $this->citationDetails($citedReferences, $availableReferences);
        $citationCompleteness = $requiredCitations === []
            ? 1.0
            : round(count(array_intersect($requiredCitations, $citedReferences)) / count($requiredCitations), 6);
        $sourceCoverage = $this->sourceCoverage($answer, $expectedSourceTypes);
        $unsupportedFacts = $this->unsupportedFacts($answer, $expectedFacts);

        $groundedness = match (true) {
            $unsupportedFacts === [] && $citationDetails['unsupported_citations'] === [] => 'supported',
            count($unsupportedFacts) < count($expectedFacts) => 'partially_supported',
            default => 'unsupported',
        };

        $warnings = [];
        if ($citationDetails['invalid_citations'] !== []) {
            $warnings[] = 'invalid citation';
        }
        if ($citationDetails['unsupported_citations'] !== []) {
            $warnings[] = 'unsupported citation';
        }
        if ($citationCompleteness < 1.0) {
            $warnings[] = 'missing citation';
        }
        if ($unsupportedFacts !== []) {
            $warnings[] = 'unsupported claim';
        }

        return new AnswerEvaluation(
            groundedness: $groundedness,
            citationCorrectness: $citationDetails['citation_correctness'],
            citationCompleteness: $citationCompleteness,
            sourceCoverageScore: (float) $sourceCoverage['coverage'],
            answerCompleteness: $expectedFacts === [] ? 1.0 : round((count($expectedFacts) - count($unsupportedFacts)) / count($expectedFacts), 6),
            unsupportedClaims: $unsupportedFacts,
            warnings: array_values(array_unique($warnings)),
            citationDetails: $citationDetails,
            sourceCoverage: $sourceCoverage,
        );
    }

    /**
     * @param  list<string>  $citedReferences
     * @param  list<string>  $availableReferences
     * @return array<string, mixed>
     */
    private function citationDetails(array $citedReferences, array $availableReferences): array
    {
        $invalid = array_values(array_filter(
            $citedReferences,
            static fn (string $reference): bool => ! KnowledgeDocumentRecord::query()->where('reference', $reference)->exists(),
        ));
        $unsupported = array_values(array_diff($citedReferences, $availableReferences));
        $validSupported = array_values(array_diff($citedReferences, [...$invalid, ...$unsupported]));

        return [
            'cited_references' => $citedReferences,
            'available_references' => $availableReferences,
            'invalid_citations' => $invalid,
            'unsupported_citations' => $unsupported,
            'valid_supported_citations' => $validSupported,
            'citation_correctness' => $citedReferences === [] ? 0.0 : round(count($validSupported) / count($citedReferences), 6),
        ];
    }

    /**
     * @param  list<string>  $expectedSourceTypes
     * @return array<string, mixed>
     */
    private function sourceCoverage(AnswerData $answer, array $expectedSourceTypes): array
    {
        $actual = array_values(array_unique(array_map(
            static fn (RetrievalContextDocument $document): string => $document->candidate->document->sourceType,
            $answer->supportingDocuments,
        )));
        $found = array_values(array_intersect($expectedSourceTypes, $actual));

        return [
            'expected_source_types' => $expectedSourceTypes,
            'actual_source_types' => $actual,
            'found_source_types' => $found,
            'missing_source_types' => array_values(array_diff($expectedSourceTypes, $actual)),
            'coverage' => $expectedSourceTypes === [] ? 1.0 : round(count($found) / count($expectedSourceTypes), 6),
        ];
    }

    /**
     * @param  list<string>  $expectedFacts
     * @return list<string>
     */
    private function unsupportedFacts(AnswerData $answer, array $expectedFacts): array
    {
        $evidence = Str::lower($answer->answer.' '.implode(' ', array_map(
            static fn (RetrievalContextDocument $document): string => $document->candidate->document->content,
            $answer->supportingDocuments,
        )));

        return array_values(array_filter(
            $expectedFacts,
            static fn (string $fact): bool => ! Str::contains($evidence, Str::lower($fact)),
        ));
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values),
            static fn (string $value): bool => $value !== '',
        ));
    }
}
