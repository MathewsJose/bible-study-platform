<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\DTOs\AnswerData;

final readonly class AnswerEvaluationService
{
    /** @return array<string, float|int> */
    public function evaluate(AnswerData $answer): array
    {
        $citationCount = count($answer->citations);
        $citationCoverage = $citationCount === 0 ? 0.0 : min(1.0, substr_count($answer->answer, '[') / $citationCount);

        return [
            'groundedness' => $answer->confidence->score,
            'citation_coverage' => round($citationCoverage, 4),
            'faithfulness' => $answer->warnings === [] ? 1.0 : 0.75,
            'response_completeness' => str_word_count($answer->answer) > 12 ? 1.0 : 0.5,
            'latency_ms' => $answer->latencyMs,
        ];
    }
}
