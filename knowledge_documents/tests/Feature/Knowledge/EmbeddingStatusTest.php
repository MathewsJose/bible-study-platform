<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbeddingStatusTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(KnowledgeDocumentService::class);
    }

    public function test_new_document_defaults_to_pending(): void
    {
        $data = [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => 'Test Bible',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'Genesis 1:1',
            'title' => 'In the beginning',
            'content' => 'In the beginning God created heaven, and earth.',
            'metadata' => [],
        ];

        $document = $this->service->create($data);

        $this->assertEquals(EmbeddingStatus::Pending->value, $document->embeddingStatus);
        
        $record = KnowledgeDocumentRecord::find($document->id);
        $this->assertEquals(EmbeddingStatus::Pending, $record->embedding_status);
        $this->assertNull($record->embedded_at);
    }

    public function test_document_with_embedding_is_ready(): void
    {
        $data = [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => 'Test Bible',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'Genesis 1:1',
            'title' => 'In the beginning',
            'content' => 'In the beginning God created heaven, and earth.',
            'metadata' => [],
            'embedding' => array_fill(0, 1536, 0.1),
        ];

        $document = $this->service->create($data);

        $this->assertEquals(EmbeddingStatus::Ready->value, $document->embeddingStatus);
        
        $record = KnowledgeDocumentRecord::find($document->id);
        $this->assertEquals(EmbeddingStatus::Ready, $record->embedding_status);
        $this->assertNotNull($record->embedded_at);
    }

    public function test_import_sets_pending_status_for_new_records(): void
    {
        $data = [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => 'Test Bible',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'Genesis 1:1',
            'title' => 'In the beginning',
            'content' => 'In the beginning God created heaven, and earth.',
            'metadata' => [],
        ];

        $this->service->import($data);

        $record = KnowledgeDocumentRecord::first();
        $this->assertEquals(EmbeddingStatus::Pending, $record->embedding_status);
    }

    public function test_import_resets_to_pending_on_content_change(): void
    {
        // 1. Create initial record as Ready
        $record = KnowledgeDocumentRecord::factory()->create([
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => 'Test Bible',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'Genesis 1:1',
            'content' => 'Old content',
            'embedding_status' => EmbeddingStatus::Ready,
            'embedding' => json_encode(array_fill(0, 1536, 0.1)),
            'embedded_at' => now(),
        ]);

        $data = [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => 'Test Bible',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'Genesis 1:1',
            'title' => 'In the beginning',
            'content' => 'New content',
            'metadata' => [],
        ];

        // 2. Import with changed content
        $this->service->import($data);

        $record->refresh();
        $this->assertEquals(EmbeddingStatus::Pending, $record->embedding_status);
        $this->assertNull($record->embedding);
        $this->assertNull($record->embedded_at);
    }

    public function test_update_resets_to_pending_on_content_change(): void
    {
        $record = KnowledgeDocumentRecord::factory()->create([
            'content' => 'Old content',
            'embedding_status' => EmbeddingStatus::Ready,
            'embedding' => json_encode(array_fill(0, 1536, 0.1)),
            'embedded_at' => now(),
        ]);

        $this->service->update($record->id, ['content' => 'New content']);

        $record->refresh();
        $this->assertEquals(EmbeddingStatus::Pending, $record->embedding_status);
        $this->assertNull($record->embedding);
    }
}
