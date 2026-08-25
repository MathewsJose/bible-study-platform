<?php

declare(strict_types=1);

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

use function Pest\Laravel\getJson;

function referenceResolutionDocument(
    string $reference,
    string $sourceName,
    string $content,
    string $sourceType = SourceType::BibleVerse->value,
    string $translation = 'unknown',
): KnowledgeDocumentRecord {
    return KnowledgeDocumentRecord::factory()->create([
        'source_type' => $sourceType,
        'source_name' => $sourceName,
        'tradition' => Tradition::Catholic->value,
        'reference' => $reference,
        'title' => $reference,
        'content' => $content,
        'metadata' => [
            'translation' => $translation,
            'book' => str($reference)->before(' ')->toString(),
        ],
    ]);
}

it('prefers the configured canonical Bible source for ambiguous plain references', function (): void {
    config()->set('knowledge.reference_resolution.canonical_bible_source_name', 'Douay-Rheims Bible');

    referenceResolutionDocument('John 1:1', 'Bible', 'Legacy Bible text.');
    referenceResolutionDocument('John 1:1', 'Douay-Rheims Bible', 'Canonical Douay text.', translation: 'douay_rheims');

    getJson('/api/v1/knowledge/reference/'.rawurlencode('John 1:1'))
        ->assertOk()
        ->assertJsonPath('data.document.reference', 'John 1:1')
        ->assertJsonPath('data.document.source_name', 'Douay-Rheims Bible')
        ->assertJsonPath('data.document.content', 'Canonical Douay text.');
});

it('lets explicit source selection override the canonical Bible source', function (): void {
    referenceResolutionDocument('John 1:1', 'Bible', 'Legacy Bible text.');
    referenceResolutionDocument('John 1:1', 'Douay-Rheims Bible', 'Canonical Douay text.', translation: 'douay_rheims');

    getJson('/api/v1/knowledge/reference/'.rawurlencode('John 1:1').'?source_name='.rawurlencode('Bible'))
        ->assertOk()
        ->assertJsonPath('data.document.source_name', 'Bible')
        ->assertJsonPath('data.document.content', 'Legacy Bible text.');
});

it('lets explicit translation selection resolve the matching Bible source', function (): void {
    referenceResolutionDocument('John 1:1', 'Bible', 'Legacy Bible text.', translation: 'legacy');
    referenceResolutionDocument('John 1:1', 'Douay-Rheims Bible', 'Canonical Douay text.', translation: 'douay_rheims');

    getJson('/api/v1/knowledge/reference/'.rawurlencode('John 1:1').'?translation=legacy')
        ->assertOk()
        ->assertJsonPath('data.document.source_name', 'Bible')
        ->assertJsonPath('data.document.content', 'Legacy Bible text.');
});

it('keeps non Bible reference resolution unchanged', function (): void {
    referenceResolutionDocument(
        reference: 'CCC 457',
        sourceName: 'Catechism of the Catholic Church',
        content: 'The Word became flesh for our salvation.',
        sourceType: SourceType::Catechism->value,
    );

    getJson('/api/v1/knowledge/reference/'.rawurlencode('CCC 457'))
        ->assertOk()
        ->assertJsonPath('data.document.reference', 'CCC 457')
        ->assertJsonPath('data.document.source_type', SourceType::Catechism->value);
});

it('keeps the existing not found error contract', function (): void {
    getJson('/api/v1/knowledge/reference/'.rawurlencode('CCC 999999'))
        ->assertNotFound()
        ->assertJsonPath('message', 'Reference not found.')
        ->assertJsonStructure(['errors' => ['reference']]);
});

it('resolves multiple Bible sources deterministically regardless of insertion order', function (): void {
    config()->set('knowledge.reference_resolution.canonical_bible_source_name', 'Douay-Rheims Bible');

    referenceResolutionDocument('John 1:1', 'Other Bible', 'Other Bible text.', translation: 'other');
    referenceResolutionDocument('John 1:1', 'Douay-Rheims Bible', 'Canonical Douay text.', translation: 'douay_rheims');
    referenceResolutionDocument('John 1:1', 'Bible', 'Legacy Bible text.', translation: 'legacy');

    getJson('/api/v1/knowledge/reference/'.rawurlencode('John 1:1'))
        ->assertOk()
        ->assertJsonPath('data.document.source_name', 'Douay-Rheims Bible');
});

it('returns validation errors for unsupported explicit source types', function (): void {
    getJson('/api/v1/knowledge/reference/'.rawurlencode('John 1:1').'?source_type=not_real')
        ->assertBadRequest()
        ->assertJsonPath('message', 'Invalid reference resolution request.')
        ->assertJsonStructure(['errors' => ['source_type']]);
});

it('resolves representative Douay Rheims references through the reference endpoint', function (string $reference): void {
    referenceResolutionDocument($reference, 'Douay-Rheims Bible', "{$reference} Douay text.", translation: 'douay_rheims');

    getJson('/api/v1/knowledge/reference/'.rawurlencode($reference))
        ->assertOk()
        ->assertJsonPath('data.document.reference', $reference)
        ->assertJsonPath('data.document.source_name', 'Douay-Rheims Bible');
})->with([
    'John 3:16',
    'John 6:51',
    'John 19:30',
    'John 20:19',
    'Tobit 1:1',
    'Judith 1:1',
    'Wisdom 1:1',
    'Sirach 1:1',
    'Baruch 1:1',
    '1 Maccabees 1:1',
    '2 Maccabees 1:1',
]);
