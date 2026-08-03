<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_text_search_filters_by_source_name(): void
    {
        KnowledgeDocumentRecord::factory()->create([
            'title' => 'Common Title',
            'content' => 'Content here',
            'source_name' => 'Source A',
        ]);
        KnowledgeDocumentRecord::factory()->create([
            'title' => 'Common Title',
            'content' => 'Content here',
            'source_name' => 'Source B',
        ]);

        $response = $this->postJson('/api/documents/search', [
            'query' => 'Common',
            'source_name' => 'Source A',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document.source_name', 'Source A');
    }

    public function test_full_text_search_filters_by_metadata_book(): void
    {
        KnowledgeDocumentRecord::factory()->create([
            'title' => 'Bible Verse',
            'content' => 'In the beginning...',
            'metadata' => ['book' => 'Genesis', 'chapter' => 1],
        ]);
        KnowledgeDocumentRecord::factory()->create([
            'title' => 'Bible Verse',
            'content' => 'In the beginning...',
            'metadata' => ['book' => 'John', 'chapter' => 1],
        ]);

        $response = $this->postJson('/api/documents/search', [
            'query' => 'beginning',
            'book' => 'Genesis',
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document.metadata.book', 'Genesis');
    }

    public function test_full_text_search_filters_by_metadata_chapter(): void
    {
        KnowledgeDocumentRecord::factory()->create([
            'title' => 'Bible Verse',
            'content' => 'In the beginning was the Word, and the Word was God.',
            'metadata' => ['book' => 'John', 'chapter' => 1],
        ]);
        KnowledgeDocumentRecord::factory()->create([
            'title' => 'Bible Verse',
            'content' => 'In the beginning was the Word, and the Word was God.',
            'metadata' => ['book' => 'John', 'chapter' => 2],
        ]);

        $response = $this->postJson('/api/documents/search', [
            'query' => 'God',
            'book' => 'John',
            'chapter' => 1,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document.metadata.chapter', 1);
    }

    public function test_semantic_search_applies_filters(): void
    {
        $embeddingProvider = new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return [0.1]; }
            public function embedMany(array $texts): array { return [[0.1]]; }
            public function identifier(): string { return 'test'; }
        };

        $mockRepo = \Mockery::mock(KnowledgeDocumentRepositoryInterface::class);
        app()->instance(KnowledgeDocumentRepositoryInterface::class, $mockRepo);
        app()->instance(EmbeddingProviderInterface::class, $embeddingProvider);

        $filters = [
            'source_type' => SourceType::BibleVerse->value,
            'tradition' => Tradition::Catholic->value,
            'book' => 'Matthew',
            'chapter' => 5,
            'source_name' => 'Douay-Rheims',
        ];

        $mockRepo->shouldReceive('semanticSearch')
            ->once()
            ->with(\Mockery::any(), \Mockery::any(), \Mockery::any(), \Mockery::any(), $filters)
            ->andReturn(new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10));

        $this->postJson('/api/documents/semantic-search', array_merge([
            'query' => 'Blessed are the poor in spirit',
        ], $filters))
            ->assertOk();
    }
}
