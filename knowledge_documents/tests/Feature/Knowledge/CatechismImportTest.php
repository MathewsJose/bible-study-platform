<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Importers\CatechismImporter;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CatechismImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_baltimore_catechism_from_json(): void
    {
        $dir = 'storage/app/catechism-test';
        $fullDir = base_path($dir);
        if (!is_dir($fullDir)) mkdir($fullDir, 0777, true);
        
        $path = $fullDir . '/baltimore-catechism.json';
        file_put_contents($path, json_encode([
            'catechism' => 'Baltimore Catechism',
            'language' => 'en',
            'source_url' => 'https://example.com/baltimore',
            'license' => 'Public Domain',
            'lessons' => [
                [
                    'lesson' => 1,
                    'title' => 'On the End of Man',
                    'questions' => [
                        [
                            'number' => 1,
                            'question' => 'Who made the world?',
                            'answer' => 'God made the world.'
                        ],
                        [
                            'number' => 2,
                            'question' => 'Who is God?',
                            'answer' => 'God is the Creator of heaven and earth.'
                        ]
                    ]
                ]
            ]
        ], JSON_THROW_ON_ERROR));

        config(['knowledge.import.directories' => [$dir]]);

        $status = Artisan::call('knowledge', ['--no-embeddings' => true]);
        $output = Artisan::output();
        
        $this->assertEquals(0, $status, "Output: " . $output);
        
        $this->assertDatabaseCount('knowledge_documents', 2);
        $this->assertDatabaseHas('knowledge_documents', [
            'source_type' => SourceType::Catechism->value,
            'source_name' => 'Baltimore Catechism',
            'tradition' => Tradition::Catholic->value,
            'reference' => 'Baltimore Catechism Lesson 1, Question 1',
            'title' => 'Question 1: Who made the world?',
        ]);

        $this->assertDatabaseHas('import_manifests', [
            'source_name' => 'Baltimore Catechism',
            'source_type' => SourceType::Catechism->value,
            'records_created' => 2,
        ]);

        unlink($path);
        rmdir($fullDir);
    }
}
