<?php

declare(strict_types=1);

use App\Application\Knowledge\Retrieval\Experiments\ScriptureRoutingSearchService;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

function createScriptureRoutingDocument(string $sourceName, string $reference): KnowledgeDocumentRecord
{
    return KnowledgeDocumentRecord::factory()->create([
        'source_type' => 'bible_verse',
        'source_name' => $sourceName,
        'tradition' => 'catholic',
        'reference' => $reference,
        'title' => $reference,
        'content' => 'In the beginning was the Word, and the Word was with God, and the Word was God.',
        'metadata' => [
            'book' => 'John',
            'chapter' => 1,
            'verse' => 1,
            'translation' => $sourceName === 'Bible' ? 'legacy' : 'douay_rheims',
        ],
    ]);
}

it('routes exact references to the canonical Douay source by default', function (): void {
    createScriptureRoutingDocument('Bible', 'John 1:1');
    createScriptureRoutingDocument('Douay-Rheims Bible', 'John 1:1');

    $result = app(ScriptureRoutingSearchService::class)
        ->search('John 1:1', 'exact_reference_route', 5)
        ->toArray();

    expect($result['classification']['route'])->toBe('exact_reference')
        ->and($result['results'][0]['reference'])->toBe('John 1:1')
        ->and($result['results'][0]['source_name'])->toBe('Douay-Rheims Bible')
        ->and($result['results'][0]['retrieval_origin'])->toBe('exact_reference');
});

it('preserves explicit legacy source override', function (): void {
    createScriptureRoutingDocument('Bible', 'John 1:1');
    createScriptureRoutingDocument('Douay-Rheims Bible', 'John 1:1');

    $result = app(ScriptureRoutingSearchService::class)
        ->search('John 1:1', 'exact_reference_route', 5, 'Bible')
        ->toArray();

    expect($result['results'][0]['source_name'])->toBe('Bible')
        ->and($result['results'][0]['reference'])->toBe('John 1:1');
});

it('does not mutate production documents when routing', function (): void {
    createScriptureRoutingDocument('Douay-Rheims Bible', 'John 1:1');
    $before = KnowledgeDocumentRecord::query()->count();

    app(ScriptureRoutingSearchService::class)
        ->search('What does John 1:1 say?', 'exact_reference_route', 5);

    expect(KnowledgeDocumentRecord::query()->count())->toBe($before);
});

it('renders the scripture route command', function (): void {
    createScriptureRoutingDocument('Douay-Rheims Bible', 'John 1:1');

    $this->artisan('retrieval:scripture-route', [
        '--query' => 'John 1:1',
        '--mode' => 'exact_reference_route',
        '--limit' => 1,
    ])
        ->expectsOutputToContain('Sprint 33 Scripture Routing')
        ->expectsOutputToContain('Route: exact_reference')
        ->assertSuccessful();
});
