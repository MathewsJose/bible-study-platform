<?php

declare(strict_types=1);

use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('imports multiple supported files from configured directories and records manifests', function (): void {
    $importRoot = storage_path('app/testing-imports');
    @mkdir($importRoot, 0777, true);

    $biblePath = $importRoot.'/bible-john-1.json';
    file_put_contents($biblePath, json_encode([
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning was the Word.'],
            ['verse' => 2, 'text' => 'He was with God.'],
        ],
    ], JSON_THROW_ON_ERROR));

    $catechismPath = $importRoot.'/catechism-sample.txt';
    file_put_contents($catechismPath, "First catechism paragraph.\n\nSecond catechism paragraph.");

    $churchFatherPath = $importRoot.'/church-fathers-sample.txt';
    file_put_contents($churchFatherPath, "First church father paragraph.\n\nSecond church father paragraph.");

    config()->set('knowledge.import.directories', [$importRoot]);

    $status = Artisan::call('knowledge:import');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('imported: 6')
        ->and($output)->toContain('skipped: 0')
        ->and($output)->toContain('failed: 0');

    assertDatabaseCount('knowledge_documents', 6);
    assertDatabaseCount('import_manifests', 3);
    assertDatabaseHas('import_manifests', [
        'source_type' => 'bible',
        'records_created' => 2,
        'status' => 'completed',
        'importer' => 'BibleImporter',
    ]);
    assertDatabaseHas('import_manifests', [
        'source_type' => 'catechism',
        'records_created' => 2,
        'status' => 'completed',
        'importer' => 'CatechismImporter',
    ]);
    assertDatabaseHas('knowledge_documents', [
        'source_type' => SourceType::Catechism->value,
        'source_name' => 'catechism-sample.txt',
        'reference' => 'catechism-sample.txt#1',
    ]);
});

it('skips files that were already imported in a previous run', function (): void {
    $importRoot = storage_path('app/testing-imports-repeat');
    @mkdir($importRoot, 0777, true);

    $biblePath = $importRoot.'/bible-john-1.json';
    file_put_contents($biblePath, json_encode([
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning was the Word.'],
        ],
    ], JSON_THROW_ON_ERROR));

    config()->set('knowledge.import.directories', [$importRoot]);

    $firstStatus = Artisan::call('knowledge:import');
    expect($firstStatus)->toBe(Command::SUCCESS);

    $secondStatus = Artisan::call('knowledge:import');
    $secondOutput = Artisan::output();

    expect($secondStatus)->toBe(Command::SUCCESS)
        ->and($secondOutput)->toContain('imported: 0')
        ->and($secondOutput)->toContain('skipped: 1')
        ->and($secondOutput)->toContain('failed: 0');

    assertDatabaseCount('import_manifests', 1);
});

it('records failed manifests when an error occurs', function (): void {
    $importRoot = storage_path('app/testing-imports-fail');
    if (! is_dir($importRoot)) {
        mkdir($importRoot, 0777, true);
    }

    $biblePath = $importRoot.'/bible-invalid.json';
    file_put_contents($biblePath, '{"invalid": "json"'); // Malformed JSON

    config()->set('knowledge.import.directories', [$importRoot]);

    Artisan::call('knowledge:import');

    assertDatabaseHas('import_manifests', [
        'source_type' => 'bible',
        'status' => 'failed',
        'importer' => 'BibleImporter',
    ]);

    $manifest = ImportManifest::query()->where('source_type', 'bible')->where('status', 'failed')->first();
    expect($manifest->error_message)->not->toBeNull();

    // Cleanup
    unlink($biblePath);
    rmdir($importRoot);
});
