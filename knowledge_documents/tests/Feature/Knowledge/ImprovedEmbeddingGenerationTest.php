<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImprovedEmbeddingGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function getFakeProvider()
    {
        return new class implements EmbeddingProviderInterface {
            public array $processed = [];
            public bool $shouldFail = false;

            public function embed(string $text): array { return [0.1]; }
            public function embedMany(array $texts): array {
                if ($this->shouldFail) {
                    throw new \Exception('Failed to embed');
                }
                $this->processed = array_merge($this->processed, $texts);
                return array_map(fn($t) => [0.1], $texts);
            }
            public function identifier(): string { return 'improved-model'; }
        };
    }

    public function test_dry_run_only_reports_count(): void
    {
        config()->set('embeddings.dimensions', 1);

        KnowledgeDocumentRecord::factory()->count(5)->create();

        $status = Artisan::call('embeddings:generate', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertEquals(Command::SUCCESS, $status);
        $this->assertStringContainsString('Dry run: 5 knowledge documents would be processed', $output);
        $this->assertEquals(5, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Pending)->count());
    }

    public function test_limit_restricts_processing(): void
    {
        config()->set('embeddings.dimensions', 1);

        KnowledgeDocumentRecord::factory()->count(10)->create();
        
        $provider = $this->getFakeProvider();
        app()->instance(EmbeddingProviderInterface::class, $provider);

        $status = Artisan::call('embeddings:generate', ['--limit' => 3]);
        $output = Artisan::output();

        $this->assertEquals(Command::SUCCESS, $status);
        $this->assertStringContainsString('documents queued: 3', $output);
        $this->assertEquals(7, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Pending)->count());
        $this->assertEquals(3, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Ready)->count());
    }

    public function test_source_filters_work(): void
    {
        config()->set('embeddings.dimensions', 1);

        KnowledgeDocumentRecord::factory()->count(3)->create([
            'source_type' => SourceType::BibleVerse,
            'source_name' => 'Source A',
        ]);
        KnowledgeDocumentRecord::factory()->count(2)->create([
            'source_type' => SourceType::BibleVerse,
            'source_name' => 'Source B',
        ]);

        $status = Artisan::call('embeddings:generate', ['--source-name' => 'Source A']);
        
        $this->assertEquals(3, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Ready)->count());
        $this->assertEquals(2, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Pending)->count());
    }

    public function test_retry_failed_includes_failed_records(): void
    {
        config()->set('embeddings.dimensions', 1);

        KnowledgeDocumentRecord::factory()->create([
            'embedding_status' => EmbeddingStatus::Failed,
            'embedding_error' => 'Previous error',
        ]);
        KnowledgeDocumentRecord::factory()->create([
            'embedding_status' => EmbeddingStatus::Pending,
        ]);

        // 1. Without --retry-failed, only pending should be processed
        Artisan::call('embeddings:generate');
        $this->assertEquals(1, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Ready)->count());
        $this->assertEquals(1, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Failed)->count());

        // 2. With --retry-failed, both should be ready (the failed one and any new pending)
        Artisan::call('embeddings:generate', ['--retry-failed' => true]);
        $this->assertEquals(2, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Ready)->count());
        $this->assertEquals(0, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Failed)->count());
    }

    public function test_success_updates_all_fields_and_clears_error(): void
    {
        config()->set('embeddings.dimensions', 1);

        $record = KnowledgeDocumentRecord::factory()->create([
            'embedding_status' => EmbeddingStatus::Failed,
            'embedding_error' => 'Old error',
        ]);

        $provider = $this->getFakeProvider();
        app()->instance(EmbeddingProviderInterface::class, $provider);

        $status = Artisan::call('embeddings:generate', ['--retry-failed' => true]);
        
        $record->refresh();
        $this->assertEquals(EmbeddingStatus::Ready, $record->embedding_status);
        $this->assertEquals('improved-model', $record->embedding_model);
        $this->assertNotNull($record->embedded_at);
        $this->assertNull($record->embedding_error);
        $this->assertEquals([0.1], $record->embedding);
    }

    public function test_failure_sets_failed_status_and_error_message(): void
    {
        config()->set('embeddings.dimensions', 1);

        KnowledgeDocumentRecord::factory()->create();
        
        $provider = $this->getFakeProvider();
        $provider->shouldFail = true;
        app()->instance(EmbeddingProviderInterface::class, $provider);

        $status = Artisan::call('embeddings:generate');
        $output = Artisan::output();

        $this->assertEquals(Command::FAILURE, $status);
        $this->assertStringContainsString('failures: 1', $output);
        
        $record = KnowledgeDocumentRecord::first();
        $this->assertEquals(EmbeddingStatus::Failed, $record->embedding_status);
        $this->assertEquals('Failed to embed', $record->embedding_error);
    }

    public function test_document_id_and_force_regenerate_a_single_ready_document(): void
    {
        config()->set('embeddings.dimensions', 1);

        $ready = KnowledgeDocumentRecord::factory()->create([
            'embedding_status' => EmbeddingStatus::Ready,
            'embedding' => [0.9],
            'embedded_at' => now()->subDay(),
        ]);
        KnowledgeDocumentRecord::factory()->create();

        $provider = $this->getFakeProvider();
        app()->instance(EmbeddingProviderInterface::class, $provider);

        $status = Artisan::call('embeddings:generate', [
            '--document-id' => $ready->id,
            '--force' => true,
            '--batch' => 1,
        ]);

        $ready->refresh();

        $this->assertEquals(Command::SUCCESS, $status);
        $this->assertEquals([0.1], $ready->embedding);
        $this->assertEquals(1, KnowledgeDocumentRecord::where('embedding_status', EmbeddingStatus::Pending)->count());
    }
}
