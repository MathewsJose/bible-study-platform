<?php

declare(strict_types=1);

use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\EloquentEmbeddingRepository;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

it('stores embeddings and performs filtered semantic search with the local fallback', function (): void {
    $repository = new EloquentEmbeddingRepository();

    $matching = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => 'Catechism of the Catholic Church',
        'tradition' => Tradition::Catholic->value,
        'reference' => 'CCC 457',
        'title' => 'Why the Word became Flesh',
    ]);

    $other = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'Bible',
        'tradition' => Tradition::Catholic->value,
        'reference' => 'John 1:1',
    ]);

    $repository->storeEmbedding($matching->id, [1.0, 0.0, 0.0], 'local', 'test-model', 3);
    $repository->storeEmbedding($other->id, [0.0, 1.0, 0.0], 'local', 'test-model', 3);

    $results = $repository->semanticSearch([1.0, 0.0, 0.0], 10, 0.1, [
        'source_types' => [SourceType::Catechism->value],
        'tradition' => Tradition::Catholic->value,
        'source_name' => 'Catechism of the Catholic Church',
    ]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['record']->reference)->toBe('CCC 457')
        ->and($results[0]['record']->embedding_status)->toBe(EmbeddingStatus::Ready)
        ->and($results[0]['record']->embedding_provider)->toBe('local')
        ->and($results[0]['record']->embedding_model)->toBe('test-model')
        ->and($results[0]['record']->embedding_dimensions)->toBe(3)
        ->and($results[0]['score'])->toBe(1.0);
});
