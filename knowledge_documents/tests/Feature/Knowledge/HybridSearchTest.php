<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class HybridSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_hybrid_search_combines_and_ranks_results(): void
    {
        $doc1 = KnowledgeDocumentRecord::factory()->create(['reference' => 'Doc 1', 'source_name' => 'Other']);
        $doc2 = KnowledgeDocumentRecord::factory()->create(['reference' => 'Doc 2', 'source_name' => 'Douay-Rheims Bible']);

        $embeddingProvider = new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return [0.1]; }
            public function embedMany(array $texts): array { return [[0.1]]; }
            public function identifier(): string { return 'test'; }
        };

        $mockRepo = \Mockery::mock(KnowledgeDocumentRepositoryInterface::class);
        app()->instance(KnowledgeDocumentRepositoryInterface::class, $mockRepo);
        app()->instance(EmbeddingProviderInterface::class, $embeddingProvider);

        // Doc 1 has high full-text score, low semantic
        // Doc 2 has low full-text score, high semantic + priority boost
        
        $mockRepo->shouldReceive('fullTextSearch')
            ->once()
            ->andReturn([
                ['record' => $doc1, 'score' => 1.0],
                ['record' => $doc2, 'score' => 0.1],
            ]);

        $mockRepo->shouldReceive('semanticSearch')
            ->once()
            ->andReturn(new LengthAwarePaginator([
                ['record' => $doc2, 'score' => 0.9],
                ['record' => $doc1, 'score' => 0.2],
            ], 2, 10));

        $response = $this->postJson('/api/documents/hybrid-search', [
            'query' => 'test query',
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Weights: semantic 0.65, full-text 0.25, priority 0.10
        // Doc 1 Score: (0.2 * 0.65) + (1.0 * 0.25) + (0 * 0.10) = 0.13 + 0.25 = 0.38
        // Doc 2 Score: (0.9 * 0.65) + (0.1 * 0.25) + (1 * 0.10) = 0.585 + 0.025 + 0.10 = 0.71
        
        $response->assertJsonPath('data.0.document.reference', 'Doc 2')
            ->assertJsonPath('data.0.score', 0.71)
            ->assertJsonPath('data.1.document.reference', 'Doc 1')
            ->assertJsonPath('data.1.score', 0.38);
            
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'document',
                    'score',
                    'score_breakdown' => [
                        'semantic',
                        'full_text',
                        'source_priority',
                    ]
                ]
            ]
        ]);
    }
}
