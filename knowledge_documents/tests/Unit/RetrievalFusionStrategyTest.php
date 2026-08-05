<?php

declare(strict_types=1);

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Services\WeightedScoreFusionStrategy;

uses(Tests\TestCase::class);

function fusionDocument(string $id, string $reference): KnowledgeDocumentData
{
    return new KnowledgeDocumentData(
        id: $id,
        sourceType: 'catechism',
        sourceName: 'Catechism of the Catholic Church',
        tradition: 'catholic',
        reference: $reference,
        title: $reference,
        content: 'Test content',
        metadata: [],
        createdAt: '2026-01-01T00:00:00Z',
        updatedAt: '2026-01-01T00:00:00Z',
        embeddingStatus: 'ready',
    );
}

it('normalizes vector and lexical scores before weighted fusion', function (): void {
    config()->set('retrieval.hybrid.vector_weight', 0.7);
    config()->set('retrieval.hybrid.lexical_weight', 0.3);

    $docA = fusionDocument('doc-a', 'CCC 457');
    $docB = fusionDocument('doc-b', 'John 1:14');

    $results = (new WeightedScoreFusionStrategy())->fuse(
        vectorResults: [
            new RankedKnowledgeDocumentData($docA, 0.2),
            new RankedKnowledgeDocumentData($docB, 0.8),
        ],
        lexicalResults: [
            new RankedKnowledgeDocumentData($docA, 4.0),
            new RankedKnowledgeDocumentData($docB, 1.0),
        ],
        topK: 10,
    );

    expect($results)->toHaveCount(2)
        ->and($results[0]->document->reference)->toBe('John 1:14')
        ->and($results[0]->score)->toBe(0.775)
        ->and($results[1]->document->reference)->toBe('CCC 457')
        ->and($results[1]->score)->toBe(0.475);
});

it('deduplicates candidates and applies the minimum combined score', function (): void {
    config()->set('retrieval.hybrid.vector_weight', 0.5);
    config()->set('retrieval.hybrid.lexical_weight', 0.5);

    $docA = fusionDocument('doc-a', 'CCC 457');
    $docB = fusionDocument('doc-b', 'John 3:16');

    $results = (new WeightedScoreFusionStrategy())->fuse(
        vectorResults: [
            new RankedKnowledgeDocumentData($docA, 1.0),
            new RankedKnowledgeDocumentData($docB, 0.1),
        ],
        lexicalResults: [
            new RankedKnowledgeDocumentData($docA, 1.0),
        ],
        topK: 10,
        minimumScore: 0.6,
    );

    expect($results)->toHaveCount(1)
        ->and($results[0]->document->id)->toBe('doc-a')
        ->and($results[0]->scoreBreakdown)->toMatchArray([
            'vector' => 1.0,
            'lexical' => 1.0,
            'combined' => 1.0,
        ]);
});
