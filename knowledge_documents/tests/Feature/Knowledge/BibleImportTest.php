<?php

declare(strict_types=1);

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Importers\BibleImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

it('imports Bible verses from JSON as knowledge documents', function (): void {
    $path = storage_path('app/bible-import-john-1.json');
    file_put_contents($path, json_encode([
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning was the Word.'],
            ['verse' => 2, 'text' => 'He was in the beginning with God.'],
        ],
    ], JSON_THROW_ON_ERROR));

    $status = Artisan::call('bible:import', ['path' => $path]);
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('documents imported: 2')
        ->and($output)->toContain('skipped duplicates: 0')
        ->and($output)->toContain('failures: 0');

    assertDatabaseCount('knowledge_documents', 2);
    assertDatabaseHas('knowledge_documents', [
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => BibleImporter::SOURCE_NAME,
        'reference' => 'John 1:1',
        'title' => 'John 1:1',
        'content' => 'In the beginning was the Word.',
        'tradition' => Tradition::Catholic->value,
    ]);
});

it('skips duplicate Bible verse imports safely', function (): void {
    $path = storage_path('app/bible-import-duplicates.json');
    file_put_contents($path, json_encode([
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning was the Word.'],
        ],
    ], JSON_THROW_ON_ERROR));

    $firstStatus = Artisan::call('bible:import', ['path' => $path]);
    $firstOutput = Artisan::output();

    expect($firstStatus)->toBe(Command::SUCCESS)
        ->and($firstOutput)->toContain('documents imported: 1')
        ->and($firstOutput)->toContain('skipped duplicates: 0')
        ->and($firstOutput)->toContain('failures: 0');

    $secondStatus = Artisan::call('bible:import', ['path' => $path]);
    $secondOutput = Artisan::output();

    expect($secondStatus)->toBe(Command::SUCCESS)
        ->and($secondOutput)->toContain('documents imported: 0')
        ->and($secondOutput)->toContain('skipped duplicates: 1')
        ->and($secondOutput)->toContain('failures: 0');

    assertDatabaseCount('knowledge_documents', 1);
});

it('fails with validation details for invalid Bible JSON payloads', function (): void {
    $path = storage_path('app/bible-import-invalid-payload.json');
    file_put_contents($path, json_encode([
        'book' => 'John',
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning was the Word.'],
        ],
    ], JSON_THROW_ON_ERROR));

    $status = Artisan::call('bible:import', ['path' => $path]);
    $output = Artisan::output();

    expect($status)->toBe(Command::FAILURE)
        ->and($output)->toContain('Bible import validation failed.')
        ->and($output)->toContain('The chapter field is required.')
        ->and($output)->toContain('documents imported: 0')
        ->and($output)->toContain('skipped duplicates: 0')
        ->and($output)->toContain('failures: 1');
});

it('fails gracefully for malformed Bible JSON files', function (): void {
    $path = storage_path('app/bible-import-malformed.json');
    file_put_contents($path, '{"book": "John",');

    $status = Artisan::call('bible:import', ['path' => $path]);
    $output = Artisan::output();

    expect($status)->toBe(Command::FAILURE)
        ->and($output)->toContain('Bible import JSON is invalid:')
        ->and($output)->toContain('documents imported: 0')
        ->and($output)->toContain('skipped duplicates: 0')
        ->and($output)->toContain('failures: 1');
});
