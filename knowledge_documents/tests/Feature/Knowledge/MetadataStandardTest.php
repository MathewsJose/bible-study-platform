<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Infrastructure\Knowledge\Importers\BibleImporter;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetadataStandardTest extends TestCase
{
    use RefreshDatabase;

    public function test_bible_import_includes_standard_metadata(): void
    {
        $importer = app(BibleImporter::class);
        $path = storage_path('app/test_bible.json');
        
        $payload = [
            'book' => 'Genesis',
            'chapter' => 1,
            'verses' => [
                ['verse' => 1, 'text' => 'In the beginning...'],
            ],
            'source_url' => 'https://example.com/bible',
            'license' => 'CC0',
            'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
        ];

        file_put_contents($path, json_encode($payload));

        try {
            $importer->importFile($path);

            $record = KnowledgeDocumentRecord::first();
            $this->assertNotNull($record);
            
            $metadata = $record->metadata;
            $this->assertEquals('https://example.com/bible', $metadata['source_url']);
            $this->assertEquals('CC0', $metadata['license']);
            $this->assertEquals('https://creativecommons.org/publicdomain/zero/1.0/', $metadata['license_url']);
            $this->assertEquals('BibleImporter', $metadata['imported_from']);
            $this->assertArrayHasKey('imported_at', $metadata);
            $this->assertEquals('en', $metadata['language']);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_knowledge_import_includes_metadata_from_options(): void
    {
        $path = storage_path('app/test_catechism.txt');
        file_put_contents($path, "Paragraph 1\n\nParagraph 2");

        try {
            config(['knowledge.import.directories' => [storage_path('app')]]);

            $this->artisan('knowledge:import', [
                '--source-url' => 'https://example.com/catechism',
                '--license' => 'Copyright Vatican',
                '--language' => 'la',
            ]);

            $record = KnowledgeDocumentRecord::where('content', 'Paragraph 1')->first();
            $this->assertNotNull($record);

            $metadata = $record->metadata;
            $this->assertEquals('https://example.com/catechism', $metadata['source_url']);
            $this->assertEquals('Copyright Vatican', $metadata['license']);
            $this->assertEquals('la', $metadata['language']);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
