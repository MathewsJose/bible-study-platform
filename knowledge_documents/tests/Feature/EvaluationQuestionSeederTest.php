<?php

declare(strict_types=1);

use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Database\Seeders\EvaluationQuestionSeeder;

function createEvaluationReference(string $reference, string $sourceType): void
{
    KnowledgeDocumentRecord::factory()->create([
        'reference' => $reference,
        'source_type' => $sourceType,
        'source_name' => $sourceType === SourceType::Catechism->value ? 'Catechism of the Catholic Church' : 'Douay-Rheims Bible',
    ]);
}

it('stores all defined evaluation questions with coverage metadata', function (): void {
    foreach (['CCC 456', 'John 1:14', 'John 1:1', 'John 1:16', 'John 1:29', 'John 1:36'] as $reference) {
        createEvaluationReference($reference, str_starts_with($reference, 'CCC') ? SourceType::Catechism->value : SourceType::BibleVerse->value);
    }

    $this->seed(EvaluationQuestionSeeder::class);

    expect(EvaluationQuestionRecord::query()->count())->toBe(20)
        ->and(EvaluationQuestionRecord::query()->where('coverage_status', 'partially_covered')->count())->toBeGreaterThan(0)
        ->and(EvaluationQuestionRecord::query()->where('coverage_status', 'unavailable')->count())->toBeGreaterThan(0);
});

it('preserves trinity intended catechism ground truth while marking missing references', function (): void {
    createEvaluationReference('John 1:1', SourceType::BibleVerse->value);

    $this->seed(EvaluationQuestionSeeder::class);

    $question = EvaluationQuestionRecord::query()
        ->where('question', 'What does the Catholic Church teach about the Trinity?')
        ->firstOrFail();

    expect($question->intended_references)->toBe(['CCC 232', 'CCC 253', 'John 1:1'])
        ->and($question->expected_references)->toBe(['John 1:1'])
        ->and($question->missing_references)->toBe(['CCC 232', 'CCC 253'])
        ->and($question->expected_source_types)->toBe([SourceType::Catechism->value, SourceType::BibleVerse->value])
        ->and($question->coverage_status)->toBe('partially_covered');
});

it('marks fully covered partially covered and unavailable questions', function (): void {
    createEvaluationReference('John 1:29', SourceType::BibleVerse->value);
    createEvaluationReference('John 1:36', SourceType::BibleVerse->value);
    createEvaluationReference('John 1:16', SourceType::BibleVerse->value);

    $this->seed(EvaluationQuestionSeeder::class);

    expect(EvaluationQuestionRecord::query()->where('question', 'Who is the Lamb of God?')->value('coverage_status'))->toBe('fully_covered')
        ->and(EvaluationQuestionRecord::query()->where('question', 'What is grace?')->value('coverage_status'))->toBe('partially_covered')
        ->and(EvaluationQuestionRecord::query()->where('question', 'What is sanctifying grace?')->value('coverage_status'))->toBe('unavailable');
});

it('is idempotent and keeps JSON casts as arrays', function (): void {
    createEvaluationReference('John 1:1', SourceType::BibleVerse->value);

    $this->seed(EvaluationQuestionSeeder::class);
    $this->seed(EvaluationQuestionSeeder::class);

    $question = EvaluationQuestionRecord::query()->where('question', 'What does John say about the Word being God?')->firstOrFail();

    expect(EvaluationQuestionRecord::query()->count())->toBe(20)
        ->and($question->expected_references)->toBeArray()
        ->and($question->intended_references)->toBeArray()
        ->and($question->missing_references)->toBeArray()
        ->and($question->expected_source_types)->toBeArray();
});

it('diagnoses evaluation coverage and skips unavailable retrieval diagnostics', function (): void {
    createEvaluationReference('John 1:29', SourceType::BibleVerse->value);
    createEvaluationReference('John 1:36', SourceType::BibleVerse->value);

    $this->seed(EvaluationQuestionSeeder::class);

    $this->artisan('evaluate:diagnose', ['--strategy' => 'lexical', '--top-k' => 1])
        ->expectsOutputToContain('Defined questions: 20')
        ->expectsOutputToContain('Stored questions: 20')
        ->expectsOutputToContain('Unavailable:')
        ->expectsOutputToContain('Skipping retrieval diagnostics for unavailable question')
        ->assertSuccessful();
});
