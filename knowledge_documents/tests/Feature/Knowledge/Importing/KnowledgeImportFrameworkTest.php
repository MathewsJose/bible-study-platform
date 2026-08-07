<?php

declare(strict_types=1);

use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('lists registered knowledge sources', function (): void {
    $status = Artisan::call('knowledge:sources');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Bible')
        ->and($output)->toContain('Catechism')
        ->and($output)->toContain('Church Fathers');
});

it('imports one requested source through the pipeline with provenance metadata', function (): void {
    $importRoot = storage_path('app/framework-imports');
    if (! is_dir($importRoot)) {
        mkdir($importRoot, 0777, true);
    }

    file_put_contents($importRoot.'/bible-john-3.json', json_encode([
        'book' => 'John',
        'chapter' => 3,
        'language' => 'en',
        'verses' => [
            ['verse' => 16, 'text' => 'For God so loved the world.'],
        ],
    ], JSON_THROW_ON_ERROR));

    file_put_contents($importRoot.'/catechism-note.txt', 'Catechism paragraph.');

    config()->set('knowledge.import.directories', [$importRoot]);

    $status = Artisan::call('knowledge:import', [
        'source' => 'bible',
        '--no-embeddings' => true,
        '--license' => 'Public Domain',
    ]);

    expect($status)->toBe(Command::SUCCESS);

    assertDatabaseCount('knowledge_documents', 1);
    assertDatabaseHas('knowledge_documents', [
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'Bible',
        'reference' => 'John 3:16',
    ]);
    assertDatabaseHas('import_manifests', [
        'source_type' => 'bible',
        'source_name' => 'Bible',
        'status' => 'completed',
        'records_created' => 1,
    ]);

    $document = KnowledgeDocumentRecord::query()->where('reference', 'John 3:16')->firstOrFail();

    expect($document->metadata)
        ->toHaveKey('source_identifier', 'bible')
        ->toHaveKey('source_version', '1.0.0')
        ->toHaveKey('source_checksum')
        ->toHaveKey('content_checksum')
        ->toHaveKey('license', 'Public Domain');
});

it('skips unchanged files unless force is requested', function (): void {
    $importRoot = storage_path('app/framework-incremental');
    if (! is_dir($importRoot)) {
        mkdir($importRoot, 0777, true);
    }

    file_put_contents($importRoot.'/catechism-sample.txt', 'A stable catechism paragraph.');
    config()->set('knowledge.import.directories', [$importRoot]);

    expect(Artisan::call('knowledge:import', ['source' => 'catechism', '--no-embeddings' => true]))
        ->toBe(Command::SUCCESS);
    expect(Artisan::call('knowledge:import', ['source' => 'catechism', '--no-embeddings' => true]))
        ->toBe(Command::SUCCESS);

    assertDatabaseCount('knowledge_documents', 1);
    assertDatabaseCount('import_manifests', 1);

    expect(Artisan::call('knowledge:import', ['source' => 'catechism', '--force' => true, '--no-embeddings' => true]))
        ->toBe(Command::SUCCESS);

    assertDatabaseCount('knowledge_documents', 1);
    expect(ImportManifest::query()->count())->toBe(2);
});

it('verifies configured imports without persisting documents', function (): void {
    $importRoot = storage_path('app/framework-verify');
    if (! is_dir($importRoot)) {
        mkdir($importRoot, 0777, true);
    }

    file_put_contents($importRoot.'/church-fathers-note.txt', 'A patristic passage.');
    config()->set('knowledge.import.directories', [$importRoot]);

    $status = Artisan::call('knowledge:verify');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('verified: 1')
        ->and($output)->toContain('failed: 0');

    assertDatabaseCount('knowledge_documents', 0);
});

it('reports import status and embedding coverage', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => 'Catechism of the Catholic Church',
        'reference' => 'CCC 1',
    ]);

    $status = Artisan::call('knowledge:status');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Registered')
        ->and($output)->toContain('Catechism')
        ->and($output)->toContain('Documents');
});
