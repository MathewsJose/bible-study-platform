<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function documentPayload(array $overrides = []): array
{
    return array_merge([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'New American Bible Revised Edition',
        'tradition' => Tradition::Catholic->value,
        'reference' => 'John 3:16',
        'title' => 'The love of God',
        'content' => 'For God so loved the world that he gave his only Son.',
        'metadata' => ['book' => 'John', 'chapter' => 3, 'verse' => 16],
    ], $overrides);
}

it('creates and retrieves a knowledge document without exposing persistence internals', function (): void {
    $createResponse = postJson('/api/documents', documentPayload());

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.source_type', SourceType::BibleVerse->value)
        ->assertJsonPath('data.reference', 'John 3:16')
        ->assertJsonMissing(['embedding' => null]);

    $id = $createResponse->json('data.id');

    getJson("/api/documents/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.metadata.book', 'John');
});

it('validates source type and required content', function (): void {
    postJson('/api/documents', documentPayload([
        'source_type' => 'unsupported',
        'content' => '',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source_type', 'content']);
});

it('updates and deletes a document', function (): void {
    $record = KnowledgeDocumentRecord::factory()->create(['title' => 'Original title']);

    putJson("/api/documents/{$record->id}", ['title' => 'Updated title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title');

    deleteJson("/api/documents/{$record->id}")
        ->assertNoContent();

    assertDatabaseMissing('knowledge_documents', ['id' => $record->id]);
});

it('paginates and filters documents', function (): void {
    KnowledgeDocumentRecord::factory()->count(2)->create(['source_type' => SourceType::Catechism->value]);
    KnowledgeDocumentRecord::factory()->create(['source_type' => SourceType::Article->value]);

    getJson('/api/documents?source_type=catechism&per_page=10')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('performs full text search with a database compatible fallback', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'title' => 'Eucharistic communion',
        'content' => 'The Eucharist is the source and summit of the Christian life.',
    ]);
    KnowledgeDocumentRecord::factory()->create([
        'title' => 'Unrelated',
        'content' => 'A short note about historical context.',
    ]);

    postJson('/api/documents/search', ['query' => 'Eucharist', 'limit' => 5])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.document.title', 'Eucharistic communion');
});

it('performs semantic search with a database compatible fallback', function (): void {
    app()->instance(EmbeddingProviderInterface::class, new class implements EmbeddingProviderInterface
    {
        public function embed(string $text): array
        {
            return [1.0, 0.0, 0.0];
        }

        public function embedMany(array $texts): array
        {
            return array_map(fn (): array => [1.0, 0.0, 0.0], $texts);
        }

        public function identifier(): string
        {
            return 'test-model';
        }
    });

    KnowledgeDocumentRecord::factory()->create([
        'reference' => 'John 3:16',
        'title' => 'God so loved the world',
        'metadata' => ['book' => 'John', 'chapter' => 3],
        'embedding_status' => EmbeddingStatus::Ready,
        'embedding' => [1.0, 0.0, 0.0],
    ]);

    KnowledgeDocumentRecord::factory()->create([
        'reference' => 'John 1:1',
        'title' => 'In the beginning',
        'metadata' => ['book' => 'John', 'chapter' => 1],
        'embedding_status' => EmbeddingStatus::Ready,
        'embedding' => [0.0, 1.0, 0.0],
    ]);

    postJson('/api/documents/semantic-search', [
        'query' => 'grace and salvation',
        'score_threshold' => 0.1,
    ])
        ->assertOk()
        ->assertJsonPath('results.0.reference', 'John 3:16')
        ->assertJsonPath('results.0.score', 1)
        ->assertJsonPath('meta.total', 1);
});
