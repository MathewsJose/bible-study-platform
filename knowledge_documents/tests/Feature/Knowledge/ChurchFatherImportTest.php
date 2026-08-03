<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ChurchFatherImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_church_fathers_from_json(): void
    {
        $dir = 'storage/app/church-fathers-test';
        $fullDir = base_path($dir);
        if (!is_dir($fullDir)) mkdir($fullDir, 0777, true);
        
        $path = $fullDir . '/church-father-augustine.json';
        file_put_contents($path, json_encode([
            'author' => 'St. Augustine',
            'work' => 'Confessions',
            'century' => '4th-5th',
            'language' => 'en',
            'original_language' => 'Latin',
            'translator' => 'E.B. Pusey',
            'source_url' => 'https://example.com/augustine',
            'license' => 'Public Domain',
            'sections' => [
                [
                    'title' => 'Book 1, Chapter 1',
                    'reference' => 'Book 1.1',
                    'content' => 'Great art Thou, O Lord, and greatly to be praised.'
                ]
            ]
        ], JSON_THROW_ON_ERROR));

        config(['knowledge.import.directories' => [$dir]]);

        $status = Artisan::call('knowledge');
        
        $this->assertEquals(0, $status);
        
        $this->assertDatabaseCount('knowledge_documents', 1);
        $this->assertDatabaseHas('knowledge_documents', [
            'source_type' => SourceType::ChurchFather->value,
            'source_name' => 'St. Augustine, Confessions',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'Book 1.1',
            'title' => 'Book 1, Chapter 1',
        ]);

        $this->assertDatabaseHas('import_manifests', [
            'source_name' => 'St. Augustine, Confessions',
            'source_type' => SourceType::ChurchFather->value,
            'records_created' => 1,
        ]);

        unlink($path);
        rmdir($fullDir);
    }
}
