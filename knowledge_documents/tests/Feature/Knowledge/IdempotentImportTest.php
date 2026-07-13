<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domain\Knowledge\Enums\ImportStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Importers\BibleImporter;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotentImportTest extends TestCase
{
    use RefreshDatabase;

    private BibleImporter $importer;
    private KnowledgeDocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = app(BibleImporter::class);
        $this->service = app(KnowledgeDocumentService::class);
    }

    public function test_first_import_creates_records(): void
    {
        $payload = $this->validPayload();

        $result = $this->importer->import($payload);

        $this->assertEquals(2, $result->created);
        $this->assertEquals(0, $result->updated);
        $this->assertEquals(0, $result->skipped);
        $this->assertEquals(2, KnowledgeDocumentRecord::count());
    }

    public function test_second_identical_import_skips_records(): void
    {
        $payload = $this->validPayload();

        // First import
        $this->importer->import($payload);
        $this->assertEquals(2, KnowledgeDocumentRecord::count());

        // Second import
        $result = $this->importer->import($payload);

        $this->assertEquals(0, $result->created);
        $this->assertEquals(0, $result->updated);
        $this->assertEquals(2, $result->skipped);
        $this->assertEquals(2, KnowledgeDocumentRecord::count());
    }

    public function test_changed_content_updates_records(): void
    {
        $payload = $this->validPayload();

        // First import
        $this->importer->import($payload);

        // Modify payload
        $payload['verses'][0]['text'] = 'Changed text';

        // Second import
        $result = $this->importer->import($payload);

        $this->assertEquals(0, $result->created);
        $this->assertEquals(1, $result->updated);
        $this->assertEquals(1, $result->skipped);
        
        $this->assertEquals(2, KnowledgeDocumentRecord::count());
        $this->assertDatabaseHas('knowledge_documents', [
            'reference' => 'Genesis 1:1',
            'content' => 'Changed text',
        ]);
    }

    public function test_changed_metadata_updates_records(): void
    {
        $data = [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => 'Bible',
            'reference' => 'Genesis 1:1',
            'title' => 'Genesis 1:1',
            'content' => 'In the beginning...',
            'tradition' => Tradition::Catholic->value,
            'metadata' => ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1],
        ];

        // First import
        $this->service->import($data);

        // Change metadata
        $data['metadata']['extra'] = 'info';

        // Second import
        $status = $this->service->import($data);

        $this->assertEquals(ImportStatus::Updated, $status);
        $this->assertDatabaseHas('knowledge_documents', [
            'reference' => 'Genesis 1:1',
        ]);
        
        $record = KnowledgeDocumentRecord::where('reference', 'Genesis 1:1')->first();
        $this->assertEquals('info', $record->metadata['extra']);
    }

    public function test_changed_tradition_updates_records(): void
    {
        $data = [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => 'Bible',
            'reference' => 'Genesis 1:1',
            'title' => 'Genesis 1:1',
            'content' => 'In the beginning...',
            'tradition' => Tradition::Catholic->value,
            'metadata' => ['book' => 'Genesis', 'chapter' => 1, 'verse' => 1],
        ];

        // First import
        $this->service->import($data);

        // Change tradition
        $data['tradition'] = 'other';

        // Second import
        $status = $this->service->import($data);

        $this->assertEquals(ImportStatus::Updated, $status);
        $this->assertDatabaseHas('knowledge_documents', [
            'reference' => 'Genesis 1:1',
            'tradition' => 'other',
        ]);
    }

    private function validPayload(): array
    {
        return [
            'book' => 'Genesis',
            'chapter' => 1,
            'verses' => [
                ['verse' => 1, 'text' => 'In the beginning...'],
                ['verse' => 2, 'text' => 'The earth was without form...'],
            ],
        ];
    }
}
