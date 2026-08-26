<?php

declare(strict_types=1);

use App\Application\Knowledge\Retrieval\Experiments\ContextualBibleTextBuilder;
use App\Application\Knowledge\Retrieval\Experiments\Sprint30RetrievalDataset;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Tests\TestCase;

uses(TestCase::class);

it('defines a versioned expanded retrieval dataset', function (): void {
    $dataset = new Sprint30RetrievalDataset();

    expect($dataset->version())->toBe('retrieval-sprint-30-v1')
        ->and($dataset->questions())->toHaveCount(81)
        ->and(collect($dataset->questions())->pluck('category')->unique()->count())->toBeGreaterThan(10)
        ->and(collect($dataset->questions())->where('category', 'exact_scripture'))->toHaveCount(15);
});

it('builds contextual Bible text without losing the target reference', function (): void {
    $document = new KnowledgeDocumentRecord([
        'source_type' => 'bible_verse',
        'source_name' => 'Douay-Rheims Bible',
        'tradition' => 'catholic',
        'reference' => 'John 1:1',
        'title' => 'John 1:1',
        'content' => 'In the beginning was the Word, and the Word was with God, and the Word was God.',
        'metadata' => ['book' => 'John', 'chapter' => 1, 'verse' => 1],
    ]);

    $text = (new ContextualBibleTextBuilder())->build($document, 0);

    expect($text)->toContain('Reference: John 1:1')
        ->and($text)->toContain('Target John 1:1')
        ->and($text)->toContain('Douay-Rheims Bible');
});
