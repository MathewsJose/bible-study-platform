<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Importers\DouayRheimsImporter;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DouayRheimsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_douay_rheims_importer_normalizes_verses_correctly(): void
    {
        $importer = app(DouayRheimsImporter::class);
        $path = storage_path('app/douay-rheims-test.json');

        $payload = [
            'book' => 'John',
            'book_abbreviation' => 'Jn',
            'chapter' => 3,
            'testament' => 'New Testament',
            'verses' => [
                ['verse' => 16, 'text' => 'For God so loved the world...'],
            ],
            'source_url' => 'https://example.com/dr',
            'license' => 'Public Domain',
        ];

        file_put_contents($path, json_encode($payload));

        try {
            $result = $importer->importFile($path);

            $this->assertEquals(2, $result->created); // 1 verse + 1 chapter
            
            // Verify verse
            $verse = KnowledgeDocumentRecord::where('source_type', SourceType::BibleVerse->value)->first();
            $this->assertNotNull($verse);
            $this->assertEquals(DouayRheimsImporter::SOURCE_NAME, $verse->source_name);
            $this->assertEquals('John 3:16', $verse->reference);
            $this->assertEquals('John 3:16', $verse->title);
            $this->assertEquals('For God so loved the world...', $verse->content);
            $this->assertEquals(Tradition::Catholic->value, $verse->tradition);

            $vMetadata = $verse->metadata;
            $this->assertEquals('John', $vMetadata['book']);
            $this->assertEquals('Jn', $vMetadata['book_abbreviation']);
            $this->assertEquals(3, $vMetadata['chapter']);
            $this->assertEquals(16, $vMetadata['verse']);
            $this->assertEquals('New Testament', $vMetadata['testament']);
            $this->assertEquals('catholic', $vMetadata['canon']);
            $this->assertEquals('douay_rheims', $vMetadata['translation']);
            $this->assertEquals('en', $vMetadata['language']);
            $this->assertEquals('https://example.com/dr', $vMetadata['source_url']);
            $this->assertEquals('Public Domain', $vMetadata['license']);

            // Verify chapter
            $chapter = KnowledgeDocumentRecord::where('source_type', SourceType::BibleChapter->value)->first();
            $this->assertNotNull($chapter);
            $this->assertEquals(DouayRheimsImporter::SOURCE_NAME, $chapter->source_name);
            $this->assertEquals('John 3', $chapter->reference);
            $this->assertEquals('John Chapter 3', $chapter->title);
            $this->assertEquals('[16] For God so loved the world...', $chapter->content);
            
            $cMetadata = $chapter->metadata;
            $this->assertEquals('John', $cMetadata['book']);
            $this->assertEquals(3, $cMetadata['chapter']);
            $this->assertEquals(1, $cMetadata['verse_count']);
            $this->assertEquals('catholic', $cMetadata['canon']);
            $this->assertEquals('douay_rheims', $cMetadata['translation']);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_knowledge_import_command_detects_douay_rheims_files(): void
    {
        // Create a directory structure that matches the detection rule
        $dir = storage_path('app/imports/sources/bible/douay-rheims');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/john-3.json';
        file_put_contents($path, json_encode([
            'book' => 'John',
            'chapter' => 3,
            'verses' => [
                ['verse' => 16, 'text' => 'For God so loved the world...'],
            ],
        ]));

        try {
            config(['knowledge.import.directories' => [$dir]]);

            Artisan::call('knowledge:import');
            
            $record = KnowledgeDocumentRecord::first();
            $this->assertNotNull($record);
            $this->assertEquals(DouayRheimsImporter::SOURCE_NAME, $record->source_name);
            $this->assertEquals('douay_rheims', $record->metadata['translation']);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
            // We don't necessarily need to remove the dirs, but it's cleaner
        }
    }

    public function test_douay_rheims_import_is_idempotent(): void
    {
        $importer = app(DouayRheimsImporter::class);
        $path = storage_path('app/dr-idempotency.json');

        $payload = [
            'book' => 'John',
            'chapter' => 3,
            'verses' => [
                ['verse' => 16, 'text' => 'For God so loved the world...'],
            ],
        ];

        file_put_contents($path, json_encode($payload));

        try {
            // First import
            $result1 = $importer->importFile($path);
            $this->assertEquals(2, $result1->created);

            // Second identical import
            $result2 = $importer->importFile($path);
            $this->assertEquals(0, $result2->created);
            $this->assertEquals(2, $result2->skipped);

            // Third import with change
            $payload['verses'][0]['text'] = 'Updated text...';
            file_put_contents($path, json_encode($payload));
            
            $result3 = $importer->importFile($path);
            $this->assertEquals(0, $result3->created);
            $this->assertEquals(2, $result3->updated);

            $this->assertEquals(2, KnowledgeDocumentRecord::count());
            $this->assertEquals('Updated text...', KnowledgeDocumentRecord::where('source_type', SourceType::BibleVerse->value)->first()->content);
            $this->assertEquals('[16] Updated text...', KnowledgeDocumentRecord::where('source_type', SourceType::BibleChapter->value)->first()->content);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_douay_rheims_chapter_concatenation(): void
    {
        $importer = app(DouayRheimsImporter::class);
        $path = storage_path('app/dr-concat.json');

        $payload = [
            'book' => 'Genesis',
            'chapter' => 1,
            'verses' => [
                ['verse' => 1, 'text' => 'In the beginning...'],
                ['verse' => 2, 'text' => 'And the earth was void...'],
            ],
        ];

        file_put_contents($path, json_encode($payload));

        try {
            $importer->importFile($path);

            $chapter = KnowledgeDocumentRecord::where('source_type', SourceType::BibleChapter->value)
                ->where('reference', 'Genesis 1')
                ->first();

            $this->assertNotNull($chapter);
            $this->assertEquals('[1] In the beginning... [2] And the earth was void...', $chapter->content);
            $this->assertEquals(2, $chapter->metadata['verse_count']);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
