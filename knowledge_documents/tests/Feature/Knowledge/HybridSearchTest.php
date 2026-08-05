<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HybridSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_hybrid_search_combines_and_ranks_results(): void
    {
        config()->set('embeddings.dimensions', 1);
        config()->set('retrieval.hybrid.vector_weight', 0.70);
        config()->set('retrieval.hybrid.lexical_weight', 0.30);

        $doc1 = KnowledgeDocumentRecord::factory()->create(['reference' => 'Doc 1', 'source_name' => 'Other']);
        $doc2 = KnowledgeDocumentRecord::factory()->create(['reference' => 'Doc 2', 'source_name' => 'Douay-Rheims Bible']);

        $embeddingProvider = new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return [0.1]; }
            public function embedMany(array $texts): array { return [[0.1]]; }
            public function identifier(): string { return 'test'; }
        };

        $mockRepo = \Mockery::mock(KnowledgeDocumentRepositoryInterface::class);
        $mockEmbeddingRepo = \Mockery::mock(EmbeddingRepositoryInterface::class);
        app()->instance(KnowledgeDocumentRepositoryInterface::class, $mockRepo);
        app()->instance(EmbeddingRepositoryInterface::class, $mockEmbeddingRepo);
        app()->instance(EmbeddingProviderInterface::class, $embeddingProvider);

        // Doc 1 has high lexical score, low vector score.
        // Doc 2 has low lexical score and high vector score.

        $mockRepo->shouldReceive('fullTextSearch')
            ->once()
            ->andReturn([
                ['record' => $doc1, 'score' => 1.0],
                ['record' => $doc2, 'score' => 0.1],
            ]);

        $mockEmbeddingRepo->shouldReceive('semanticSearch')
            ->once()
            ->andReturn([
                ['record' => $doc2, 'score' => 0.9],
                ['record' => $doc1, 'score' => 0.2],
            ]);

        $response = $this->postJson('/api/documents/hybrid-search', [
            'query' => 'test query',
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $response->assertJsonPath('data.0.reference', 'Doc 2')
            ->assertJsonPath('data.0.vector_score', 1)
            ->assertJsonPath('data.0.lexical_score', 0.1)
            ->assertJsonPath('data.0.combined_score', 0.73)
            ->assertJsonPath('data.1.reference', 'Doc 1')
            ->assertJsonPath('data.1.vector_score', 0.222222)
            ->assertJsonPath('data.1.lexical_score', 1)
            ->assertJsonPath('data.1.combined_score', 0.455555);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'source_type',
                    'source_name',
                    'tradition',
                    'reference',
                    'title',
                    'content',
                    'vector_score',
                    'lexical_score',
                    'combined_score',
                ],
            ],
            'meta' => [
                'top_k',
                'total',
                'minimum_score',
                'vector_weight',
                'lexical_weight',
            ],
        ]);
    }
}
