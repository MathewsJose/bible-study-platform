<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\DTOs\CitationData;
use App\Application\Knowledge\Answering\DTOs\ValidationResult;

final readonly class ResponseValidator
{
    /** @param  list<CitationData>  $citations */
    public function validate(string $answer, array $citations): ValidationResult
    {
        $warnings = [];

        if (trim($answer) === '') {
            $warnings[] = 'Provider returned an empty response.';
        }

        if ((bool) config('ai.guardrails.require_citations', true) && $citations !== [] && preg_match('/\[\d+\]/', $answer) !== 1) {
            $warnings[] = 'Answer does not contain bracketed citations.';
        }

        if ($citations === []) {
            $warnings[] = 'No retrieved citations were available.';
        }

        if (preg_match('/\b(I made up|hypothetical source|unknown citation|as an AI)\b/i', $answer) === 1) {
            $warnings[] = 'Answer contains hallucination or prompt-failure indicators.';
        }

        return new ValidationResult(valid: trim($answer) !== '', warnings: array_values(array_unique($warnings)));
    }
}
