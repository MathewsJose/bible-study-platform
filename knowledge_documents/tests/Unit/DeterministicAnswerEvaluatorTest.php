<?php

declare(strict_types=1);

use App\Application\Knowledge\Answering\DTOs\AnswerData;
use App\Application\Knowledge\Answering\DTOs\AnswerDiagnostics;
use App\Application\Knowledge\Answering\DTOs\CitationData;
use App\Application\Knowledge\Answering\DTOs\ConfidenceData;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\Evaluation\Services\DeterministicAnswerEvaluator;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalContextDocument;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function answerContext(KnowledgeDocumentRecord $record): RetrievalContextDocument
{
    return new RetrievalContextDocument(
        candidate: new RetrievalCandidate(
            document: KnowledgeDocumentData::fromRecord($record),
            score: 0.95,
            scoreBreakdown: ['lexical' => 0.95],
            stages: ['lexical'],
        ),
        estimatedTokens: 16,
        provenance: ['source' => 'test'],
    );
}

function evaluationAnswer(string $answer, array $documents, array $citations): AnswerData
{
    return new AnswerData(
        question: 'Why did Jesus become man?',
        answer: $answer,
        supportingDocuments: array_map(static fn (KnowledgeDocumentRecord $record): RetrievalContextDocument => answerContext($record), $documents),
        citations: $citations,
        confidence: new ConfidenceData(0.8, [], []),
        provider: 'test-provider',
        model: 'test-model',
        latencyMs: 5,
        promptTokens: null,
        completionTokens: null,
        warnings: [],
        metadata: [],
        diagnostics: new AnswerDiagnostics([], []),
    );
}

it('scores grounded answers with complete supported citations', function (): void {
    $ccc457 = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
        'content' => 'The Word became flesh for our salvation.',
    ]);
    $john114 = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'reference' => 'John 1:14',
        'title' => 'The Word became flesh',
        'content' => 'The Word became flesh and dwelt among us.',
    ]);

    $question = EvaluationQuestionRecord::factory()->create([
        'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
        'required_citations' => ['CCC 457', 'John 1:14'],
        'expected_answer_facts' => ['The Word became flesh'],
    ]);

    $evaluation = app(DeterministicAnswerEvaluator::class)->evaluate(
        evaluationAnswer('The Word became flesh for our salvation [1][2].', [$ccc457, $john114], [
            new CitationData(1, $ccc457->id, 'CCC 457', $ccc457->title, $ccc457->source_type, $ccc457->source_name, 0.95),
            new CitationData(2, $john114->id, 'John 1:14', $john114->title, $john114->source_type, $john114->source_name, 0.94),
        ]),
        $question,
    );

    expect($evaluation->groundedness)->toBe('supported')
        ->and($evaluation->citationCorrectness)->toBe(1.0)
        ->and($evaluation->citationCompleteness)->toBe(1.0)
        ->and($evaluation->sourceCoverageScore)->toBe(1.0)
        ->and($evaluation->warnings)->toBe([]);
});

it('flags unsupported citations missing citations and unsupported expected facts', function (): void {
    $ccc457 = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'reference' => 'CCC 457',
        'content' => 'The Word became flesh for our salvation.',
    ]);
    KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 999']);

    $question = EvaluationQuestionRecord::factory()->create([
        'expected_source_types' => [SourceType::Catechism->value, SourceType::BibleVerse->value],
        'required_citations' => ['CCC 457', 'John 1:14'],
        'expected_answer_facts' => ['John says the Word became flesh', 'salvation'],
    ]);

    $evaluation = app(DeterministicAnswerEvaluator::class)->evaluate(
        evaluationAnswer('The answer mentions salvation [1][2][3].', [$ccc457], [
            new CitationData(1, $ccc457->id, 'CCC 457', $ccc457->title, $ccc457->source_type, $ccc457->source_name, 0.95),
            new CitationData(2, 'missing-id', 'CCC 999', 'Unsupported', SourceType::Catechism->value, 'Test', 0.5),
            new CitationData(3, 'missing-id', 'Not Real 1', 'Invalid', SourceType::Catechism->value, 'Test', 0.1),
        ]),
        $question,
    );

    expect($evaluation->groundedness)->toBe('partially_supported')
        ->and($evaluation->warnings)->toContain('invalid citation')
        ->and($evaluation->warnings)->toContain('unsupported citation')
        ->and($evaluation->warnings)->toContain('missing citation')
        ->and($evaluation->warnings)->toContain('unsupported claim')
        ->and($evaluation->sourceCoverage['missing_source_types'])->toBe([SourceType::BibleVerse->value]);
});
