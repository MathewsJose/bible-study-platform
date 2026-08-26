<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class Sprint30RetrievalDataset
{
    /**
     * @return list<array{category: string, question: string, expected_references: list<string>, expected_source_types: list<string>}>
     */
    public function questions(): array
    {
        return array_values(array_map(
            static fn (array $question): array => [
                'category' => (string) $question['category'],
                'question' => (string) $question['question'],
                'expected_references' => array_values(array_map('strval', $question['expected_references'] ?? [])),
                'expected_source_types' => array_values(array_map('strval', $question['expected_source_types'] ?? [])),
            ],
            (array) config('retrieval_sprint30.questions', []),
        ));
    }

    public function version(): string
    {
        return (string) config('retrieval_sprint30.dataset_version', 'retrieval-sprint-30-v1');
    }

    /**
     * @return array{total: int, valid: int, missing_references: list<array{question: string, references: list<string>}>}
     */
    public function validate(): array
    {
        $missing = [];

        foreach ($this->questions() as $question) {
            $references = $question['expected_references'];
            $existing = KnowledgeDocumentRecord::query()
                ->whereIn('reference', $references)
                ->pluck('reference')
                ->map(static fn (mixed $reference): string => (string) $reference)
                ->unique()
                ->values()
                ->all();

            $missingReferences = array_values(array_diff($references, $existing));
            if ($missingReferences !== []) {
                $missing[] = [
                    'question' => $question['question'],
                    'references' => $missingReferences,
                ];
            }
        }

        return [
            'total' => count($this->questions()),
            'valid' => count($this->questions()) - count($missing),
            'missing_references' => $missing,
        ];
    }
}
