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
        $defined = 0;
        $fullyCovered = 0;
        $partiallyCovered = 0;
        $unavailable = 0;

        foreach ($this->questions() as $question) {
            $defined++;
            $existingReferences = $this->existingReferences($question['expected_references']);
            $missingReferences = array_values(array_diff($question['expected_references'], $existingReferences));
            $coverageStatus = $this->coverageStatus($existingReferences, $missingReferences);

            match ($coverageStatus) {
                'fully_covered' => $fullyCovered++,
                'partially_covered' => $partiallyCovered++,
                default => $unavailable++,
            };

            EvaluationQuestionRecord::query()->updateOrCreate(
                ['question' => $question['question']],
                [
                    'expected_references' => $existingReferences,
                    'intended_references' => $question['expected_references'],
                    'missing_references' => $missingReferences,
                    'expected_source_types' => $question['expected_source_types'],
                    'expected_answer_facts' => $question['expected_answer_facts'] ?? [],
                    'required_citations' => array_values(array_intersect($question['expected_references'], $existingReferences)),
                    'coverage_status' => $coverageStatus,
                    'category' => $question['category'],
                    'difficulty' => $question['difficulty'],
                    'metadata' => [
                        'requires_multiple_sources' => count(array_unique($question['expected_source_types'])) > 1,
                        'dataset_version' => config('agent_observability.evaluation.dataset_versions.retrieval', 'retrieval-v1'),
                    ],
                    'notes' => $this->notes($question['notes'] ?? null, $missingReferences),
                ],
            );

            if ($coverageStatus !== 'fully_covered') {
                $this->command?->warn($this->coverageMessage($question['question'], $existingReferences, $missingReferences));
            }
        }

        $this->command?->info("{$defined} evaluation questions defined");
        $this->command?->info("{$fullyCovered} questions fully covered");
        $this->command?->info("{$partiallyCovered} questions partially covered");
        $this->command?->info("{$unavailable} questions unavailable");
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
     * @param  list<string>  $existingReferences
     * @param  list<string>  $missingReferences
     */
    private function coverageStatus(array $existingReferences, array $missingReferences): string
    {
        if ($missingReferences === []) {
            return 'fully_covered';
        }

        if ($existingReferences !== []) {
            return 'partially_covered';
        }

        return 'unavailable';
    }

    /**
     * @param  list<string>  $missingReferences
     */
    private function notes(?string $notes, array $missingReferences): ?string
    {
        if ($missingReferences === []) {
            return $notes;
        }

        return trim(($notes ?? '').' Missing references in current corpus: '.implode(', ', $missingReferences));
    }

    /**
     * @param  list<string>  $existingReferences
     * @param  list<string>  $missingReferences
     */
    private function coverageMessage(string $question, array $existingReferences, array $missingReferences): string
    {
        return 'Evaluation question partially/unavailable: '.$question
            .' Available: '.($existingReferences === [] ? 'none' : implode(', ', $existingReferences))
            .' Missing: '.implode(', ', $missingReferences);
    }

    /**
     * @return list<array{question: string, expected_references: list<string>, expected_source_types: list<string>, category: string, difficulty: string, expected_answer_facts?: list<string>, notes?: string}>
     */
    private function questions(): array
    {
        return array_map(fn (array $question): array => $this->question(...$question), [
            ['Why did Jesus become man?', ['CCC 456', 'CCC 457', 'CCC 458', 'John 1:14'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'christology', 'medium', ['The Word became flesh for our salvation.']],
            ['Why did the Word become flesh?', ['CCC 456', 'CCC 457', 'John 1:14'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'christology', 'medium', ['The Word became flesh.']],
            ['How does Catholic teaching on the Incarnation relate to John 1:14?', ['CCC 456', 'CCC 457', 'John 1:14'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'christology', 'hard', ['The Word became flesh.']],
            ['Who is the Lamb of God?', ['John 1:29', 'John 1:36'], [SourceType::BibleVerse->value], 'christology', 'easy', ['Lamb of God']],
            ['What does John say about the Word being God?', ['John 1:1', 'John 1:14'], [SourceType::BibleVerse->value], 'scripture', 'easy', ['the Word was God']],
            ['Why did Jesus die on the cross?', ['CCC 599', 'CCC 600', 'CCC 601', 'John 19:30'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'salvation', 'medium', ['Christ died for salvation.']],
            ['Why did Jesus rise from the dead?', ['CCC 638', 'CCC 651', 'John 20:19'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'salvation', 'medium', ['Jesus rose from the dead.']],
            ['What does the Catholic Church teach about the Trinity?', ['CCC 232', 'CCC 253', 'John 1:1'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'trinity', 'medium', ['Trinity']],
            ['How do Scripture and Catechism support belief in the Trinity?', ['CCC 232', 'CCC 253', 'John 1:1'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'trinity', 'hard', ['Father Son and Spirit']],
            ['What is Baptism?', ['CCC 1213', 'CCC 1226', 'John 3:5'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'sacraments', 'easy', ['water and Spirit']],
            ['What does it mean to be born of water and the Spirit?', ['John 3:5', 'CCC 1215'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'sacraments', 'medium', ['water and Spirit']],
            ['What is the Eucharist?', ['CCC 1322', 'CCC 1324', 'John 6:51'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'sacraments', 'easy', ['Eucharist']],
            ['Why is the Eucharist important?', ['CCC 1324', 'CCC 1391', 'John 6:56'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'sacraments', 'medium', ['Eucharist is source and summit']],
            ['What is grace?', ['CCC 1996', 'CCC 1997', 'John 1:16'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'grace', 'easy', ['grace']],
            ['What is sanctifying grace?', ['CCC 1999', 'CCC 2000'], [SourceType::Catechism->value], 'grace', 'medium', ['sanctifying grace']],
            ['What is justification?', ['CCC 1987', 'CCC 1991', 'CCC 1992'], [SourceType::Catechism->value], 'grace', 'medium', ['justification']],
            ['How do grace and justification relate?', ['CCC 1987', 'CCC 1996', 'CCC 1999'], [SourceType::Catechism->value], 'grace', 'hard', ['justification and grace']],
            ['What does the Catholic Church teach about salvation?', ['CCC 161', 'CCC 846', 'John 3:16'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'salvation', 'medium', ['salvation']],
            ['How does God show love for the world?', ['John 3:16', 'CCC 458'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'salvation', 'easy', ['God loved the world']],
            ['What is the relationship between faith and salvation?', ['CCC 161', 'John 3:16'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'salvation', 'hard', ['faith']],
            ['What is the relationship between Scripture and Tradition?', ['CCC 80', 'CCC 81', 'CCC 82'], [SourceType::Catechism->value], 'scripture', 'medium', ['Scripture and Tradition']],
            ['What does the Catholic Church teach about biblical interpretation?', ['CCC 109', 'CCC 110', 'CCC 111'], [SourceType::Catechism->value], 'scripture', 'medium', ['biblical interpretation']],
            ['How should Scripture be read in the Church?', ['CCC 109', 'CCC 110', 'CCC 111'], [SourceType::Catechism->value], 'scripture', 'hard', ['Scripture should be read']],
            ['Why does the Catholic Church call Mary Mother of God?', ['CCC 495', 'CCC 509'], [SourceType::Catechism->value], 'mary', 'medium', ['Mother of God']],
            ["What does the Catholic Church teach about Mary's perpetual virginity?", ['CCC 499', 'CCC 500', 'CCC 510'], [SourceType::Catechism->value], 'mary', 'medium', ['perpetual virginity']],
            ['How is Mary related to the Incarnation?', ['CCC 495', 'CCC 509', 'John 1:14'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'mary', 'hard', ['Mary and Incarnation']],
            ['What does Catholic teaching say about creation?', ['CCC 279', 'CCC 280', 'John 1:3'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'creation', 'medium', ['creation']],
            ['What does John teach about creation through the Word?', ['John 1:3', 'CCC 291'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'creation', 'medium', ['all things were made']],
            ['What is prayer?', ['CCC 2559', 'CCC 2565'], [SourceType::Catechism->value], 'prayer', 'easy', ['prayer']],
            ['How does Jesus teach prayer?', ['CCC 2607', 'CCC 2608'], [SourceType::Catechism->value], 'prayer', 'medium', ['Jesus teaches prayer']],
            ['What is the Church?', ['CCC 751', 'CCC 752'], [SourceType::Catechism->value], 'church', 'easy', ['Church']],
            ['Why is the Church necessary in Catholic teaching?', ['CCC 846', 'CCC 849'], [SourceType::Catechism->value], 'church', 'hard', ['Church and salvation']],
            ['What is moral conscience?', ['CCC 1776', 'CCC 1777'], [SourceType::Catechism->value], 'moral_theology', 'medium', ['conscience']],
            ['How does Catholic teaching describe sin?', ['CCC 1849', 'CCC 1850'], [SourceType::Catechism->value], 'moral_theology', 'medium', ['sin']],
            ['What is charity?', ['CCC 1822', 'CCC 1823'], [SourceType::Catechism->value], 'moral_theology', 'easy', ['charity']],
            ['What are the saints?', ['CCC 828', 'CCC 956'], [SourceType::Catechism->value], 'saints', 'medium', ['saints']],
            ['Why does the Church honor saints?', ['CCC 828', 'CCC 957'], [SourceType::Catechism->value], 'saints', 'medium', ['honor saints']],
            ['What do the Fathers say about the Incarnation?', ['Athanasius, On the Incarnation, Chapter 8', 'John 1:14'], [SourceType::ChurchFather->value, SourceType::BibleVerse->value], 'church_fathers', 'hard', ['Incarnation']],
            ['How does Augustine interpret John?', ['Augustine, Tractates on John, Tractate 2', 'John 1:1'], [SourceType::ChurchFather->value, SourceType::BibleVerse->value], 'church_fathers', 'hard', ['John']],
            ['What does Chrysostom say about John 1?', ['Chrysostom, Homilies on John, Homily 4', 'John 1:14'], [SourceType::ChurchFather->value, SourceType::BibleVerse->value], 'church_fathers', 'hard', ['John 1']],
            ['What is Catholic doctrine on the Word becoming flesh?', ['CCC 456', 'CCC 457', 'John 1:14'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'catholic_doctrine', 'medium', ['Word became flesh']],
            ['How do Bible and Catechism explain divine love?', ['John 3:16', 'CCC 458'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'biblical_cross_reference', 'medium', ['divine love']],
            ['Where does John speak of living bread?', ['John 6:51', 'CCC 1324'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'biblical_cross_reference', 'medium', ['living bread']],
            ['How does John connect Jesus to eternal life?', ['John 3:16', 'John 6:51'], [SourceType::BibleVerse->value], 'biblical_cross_reference', 'medium', ['eternal life']],
            ['What does John 20 teach about the risen Christ?', ['John 20:19', 'CCC 638'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'scripture', 'medium', ['risen Christ']],
            ['What is the source and summit of Christian life?', ['CCC 1324'], [SourceType::Catechism->value], 'sacraments', 'easy', ['source and summit']],
            ['What does the Church teach about the Word and creation?', ['CCC 291', 'John 1:3'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'creation', 'hard', ['Word and creation']],
            ['How does the Catechism describe faith?', ['CCC 150', 'CCC 161'], [SourceType::Catechism->value], 'salvation', 'easy', ['faith']],
            ['What is the role of citations in a grounded answer?', ['John 1:14', 'CCC 457'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'evaluation', 'easy', ['grounded answer']],
            ['Which sources should answer a question about the Eucharist?', ['CCC 1324', 'John 6:51'], [SourceType::Catechism->value, SourceType::BibleVerse->value], 'evaluation', 'medium', ['Eucharist sources']],
            ['How should cross-source answers handle Scripture and Catechism?', ['John 1:14', 'CCC 456'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'evaluation', 'hard', ['cross-source']],
            ['What does Catholic teaching say about apostolic Tradition?', ['CCC 80', 'CCC 81'], [SourceType::Catechism->value], 'scripture', 'easy', ['Tradition']],
            ['What is the mission of the Church?', ['CCC 849'], [SourceType::Catechism->value], 'church', 'medium', ['mission']],
            ['How does the Church understand the communion of saints?', ['CCC 946', 'CCC 956'], [SourceType::Catechism->value], 'saints', 'hard', ['communion of saints']],
            ['What does John reveal about grace?', ['John 1:16', 'CCC 1996'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'grace', 'medium', ['grace']],
            ['How does Catholic teaching connect baptism and new birth?', ['John 3:5', 'CCC 1213'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'sacraments', 'hard', ['new birth']],
            ['What does the Catechism say about charity as love?', ['CCC 1822', 'CCC 1823'], [SourceType::Catechism->value], 'moral_theology', 'easy', ['love']],
            ['How should an answer about salvation cite authority?', ['John 3:16', 'CCC 846'], [SourceType::BibleVerse->value, SourceType::Catechism->value], 'evaluation', 'medium', ['authority']],
            ['What sources support the doctrine of the Incarnation?', ['John 1:14', 'CCC 456', 'Athanasius, On the Incarnation, Chapter 8'], [SourceType::BibleVerse->value, SourceType::Catechism->value, SourceType::ChurchFather->value], 'church_fathers', 'hard', ['Incarnation sources']],
        ]);
    }

    /**
     * @param  list<string>  $expectedReferences
     * @param  list<string>  $expectedSourceTypes
     * @param  list<string>  $expectedAnswerFacts
     * @return array{question: string, expected_references: list<string>, expected_source_types: list<string>, category: string, difficulty: string, expected_answer_facts: list<string>}
     */
    private function question(string $question, array $expectedReferences, array $expectedSourceTypes, string $category, string $difficulty, array $expectedAnswerFacts = []): array
    {
        return [
            'question' => $question,
            'expected_references' => $expectedReferences,
            'expected_source_types' => $expectedSourceTypes,
            'category' => $category,
            'difficulty' => $difficulty,
            'expected_answer_facts' => $expectedAnswerFacts,
        ];
    }
}
