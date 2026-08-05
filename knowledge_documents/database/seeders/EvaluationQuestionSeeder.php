<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Database\Seeder;

final class EvaluationQuestionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->questions() as $question) {
            $existingReferences = $this->existingReferences($question['expected_references']);
            $missingReferences = array_values(array_diff($question['expected_references'], $existingReferences));

            if ($existingReferences === []) {
                $this->command?->warn('Skipping evaluation question because expected references are missing: '.$question['question'].' ['.implode(', ', $missingReferences).']');
                continue;
            }

            EvaluationQuestionRecord::query()->updateOrCreate(
                ['question' => $question['question']],
                [
                    'expected_references' => $existingReferences,
                    'expected_source_types' => $question['expected_source_types'],
                    'category' => $question['category'],
                    'notes' => $missingReferences === []
                        ? ($question['notes'] ?? null)
                        : trim(($question['notes'] ?? '').' Missing references in current corpus: '.implode(', ', $missingReferences)),
                ],
            );
        }
    }

    /**
     * @param  list<string>  $references
     * @return list<string>
     */
    private function existingReferences(array $references): array
    {
        return KnowledgeDocumentRecord::query()
            ->whereIn('reference', $references)
            ->pluck('reference')
            ->map(static fn (mixed $reference): string => (string) $reference)
            ->all();
    }

    /**
     * @return list<array{question: string, expected_references: list<string>, expected_source_types: list<string>, category: string, notes?: string}>
     */
    private function questions(): array
    {
        return [
            [
                'question' => 'Why did Jesus become man?',
                'expected_references' => ['CCC 456', 'CCC 457', 'CCC 458', 'John 1:14'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'christology',
            ],
            [
                'question' => 'Why did the Word become flesh?',
                'expected_references' => ['CCC 456', 'CCC 457', 'John 1:14'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'christology',
            ],
            [
                'question' => 'Why did Jesus die on the cross?',
                'expected_references' => ['CCC 599', 'CCC 600', 'CCC 601', 'John 19:30'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'christology',
            ],
            [
                'question' => 'Why did Jesus rise from the dead?',
                'expected_references' => ['CCC 638', 'CCC 651', 'John 20:19'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'christology',
            ],
            [
                'question' => 'What is Baptism?',
                'expected_references' => ['CCC 1213', 'CCC 1226', 'John 3:5'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'sacraments',
            ],
            [
                'question' => 'What is the Eucharist?',
                'expected_references' => ['CCC 1322', 'CCC 1324', 'John 6:51'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'sacraments',
            ],
            [
                'question' => 'Why is the Eucharist important?',
                'expected_references' => ['CCC 1324', 'CCC 1391', 'John 6:56'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'sacraments',
            ],
            [
                'question' => 'What is grace?',
                'expected_references' => ['CCC 1996', 'CCC 1997', 'John 1:16'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'grace',
            ],
            [
                'question' => 'What is sanctifying grace?',
                'expected_references' => ['CCC 1999', 'CCC 2000'],
                'expected_source_types' => [SourceType::Catechism->value],
                'category' => 'grace',
            ],
            [
                'question' => 'What does the Catholic Church teach about the Trinity?',
                'expected_references' => ['CCC 232', 'CCC 253', 'John 1:1'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'trinity',
            ],
            [
                'question' => 'Why does the Catholic Church call Mary Mother of God?',
                'expected_references' => ['CCC 495', 'CCC 509'],
                'expected_source_types' => [SourceType::Catechism->value],
                'category' => 'mary',
            ],
            [
                'question' => "What does the Catholic Church teach about Mary's perpetual virginity?",
                'expected_references' => ['CCC 499', 'CCC 500', 'CCC 510'],
                'expected_source_types' => [SourceType::Catechism->value],
                'category' => 'mary',
            ],
            [
                'question' => 'What does the Catholic Church teach about salvation?',
                'expected_references' => ['CCC 161', 'CCC 846', 'John 3:16'],
                'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
                'category' => 'salvation',
            ],
            [
                'question' => 'What is justification?',
                'expected_references' => ['CCC 1987', 'CCC 1991', 'CCC 1992'],
                'expected_source_types' => [SourceType::Catechism->value],
                'category' => 'salvation',
            ],
            [
                'question' => 'What is the relationship between Scripture and Tradition?',
                'expected_references' => ['CCC 80', 'CCC 81', 'CCC 82'],
                'expected_source_types' => [SourceType::Catechism->value],
                'category' => 'scripture',
            ],
            [
                'question' => 'What does the Catholic Church teach about biblical interpretation?',
                'expected_references' => ['CCC 109', 'CCC 110', 'CCC 111'],
                'expected_source_types' => [SourceType::Catechism->value],
                'category' => 'scripture',
            ],
            [
                'question' => 'Who is the Lamb of God?',
                'expected_references' => ['John 1:29', 'John 1:36'],
                'expected_source_types' => [SourceType::BibleVerse->value],
                'category' => 'christology',
            ],
            [
                'question' => 'What does John say about the Word being God?',
                'expected_references' => ['John 1:1', 'John 1:14'],
                'expected_source_types' => [SourceType::BibleVerse->value],
                'category' => 'scripture',
            ],
            [
                'question' => 'What does it mean to be born of water and the Spirit?',
                'expected_references' => ['John 3:5', 'CCC 1215'],
                'expected_source_types' => [SourceType::BibleVerse->value, SourceType::Catechism->value],
                'category' => 'sacraments',
            ],
            [
                'question' => 'How does God show love for the world?',
                'expected_references' => ['John 3:16', 'CCC 458'],
                'expected_source_types' => [SourceType::BibleVerse->value, SourceType::Catechism->value],
                'category' => 'salvation',
            ],
        ];
    }
}
