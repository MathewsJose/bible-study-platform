<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\Retrieval\DTOs\AnalyzedQuery;
use Illuminate\Support\Str;

final readonly class QueryAnalyzer
{
    public function analyze(string $query): AnalyzedQuery
    {
        $normalized = trim($query);
        $references = [];
        $intents = [];

        preg_match_all('/\b(?:[1-3]\s*)?[A-Z][a-z]+\s+\d+:\d+(?:[-–]\d+)?\b/', $normalized, $scriptureMatches);
        preg_match_all('/\bCCC\s+\d+\b/i', $normalized, $catechismMatches);

        foreach ($scriptureMatches[0] as $reference) {
            $references[] = trim($reference);
            $intents[] = 'scripture_reference';
        }

        foreach ($catechismMatches[0] as $reference) {
            $references[] = strtoupper(trim($reference));
            $intents[] = 'catechism_reference';
        }

        if (preg_match('/\b(Augustine|Athanasius|Chrysostom|Aquinas|Gregory|Catena Aurea)\b/i', $normalized) === 1) {
            $intents[] = 'church_father_reference';
        }

        $topics = $this->topics($normalized);
        if ($topics !== []) {
            $intents[] = 'theological_topic';
        }

        $isQuestion = str_contains($normalized, '?')
            || preg_match('/^(who|what|when|where|why|how|which)\b/i', $normalized) === 1;
        if ($isQuestion) {
            $intents[] = 'natural_language_question';
        }

        if (str_word_count($normalized) <= 4 && $references === []) {
            $intents[] = 'keyword_query';
        }

        if (count(array_unique($intents)) > 1) {
            array_unshift($intents, 'mixed_query');
        }

        if ($intents === []) {
            $intents[] = 'natural_language';
        }

        return new AnalyzedQuery(
            query: $normalized,
            intents: array_values(array_unique($intents)),
            references: array_values(array_unique($references)),
            topics: $topics,
            isQuestion: $isQuestion,
        );
    }

    /** @return list<string> */
    private function topics(string $query): array
    {
        $topics = [];
        $lower = Str::lower($query);

        foreach (array_keys((array) config('retrieval.expansions', [])) as $topic) {
            if (str_contains($lower, Str::lower((string) $topic))) {
                $topics[] = (string) $topic;
            }
        }

        return array_values(array_unique($topics));
    }
}
