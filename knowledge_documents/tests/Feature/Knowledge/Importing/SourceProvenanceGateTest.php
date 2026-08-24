<?php

declare(strict_types=1);

use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

function provenanceTestImportDirectory(string $name): string
{
    $directory = storage_path("app/{$name}");

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory;
}

function provenanceBibleFixture(string $directory): string
{
    $path = $directory.'/bible-john-3.json';
    file_put_contents($path, json_encode([
        'book' => 'John',
        'chapter' => 3,
        'translation' => 'test-fixture',
        'verses' => [
            ['verse' => 16, 'text' => 'For God so loved the world.'],
        ],
    ], JSON_THROW_ON_ERROR));

    return $path;
}

function configureSourceInventory(array $source): void
{
    config()->set('knowledge_sources.sources', [$source]);
}

function approvedBibleSource(array $overrides = []): array
{
    return array_merge([
        'id' => 'bible.test_fixture',
        'type' => 'bible',
        'name' => 'Bible Test Fixture',
        'language' => 'en',
        'license' => 'Test fixture only',
        'copyright_status' => 'verified',
        'verification_status' => 'approved',
        'rights_notes' => 'Synthetic test fixture; not a real corpus source.',
        'import_allowed' => true,
    ], $overrides);
}

it('allows an approved source to pass provenance validation and import', function (): void {
    $directory = provenanceTestImportDirectory('provenance-approved');
    provenanceBibleFixture($directory);
    config()->set('knowledge.import.directories', [$directory]);
    configureSourceInventory(approvedBibleSource());

    $status = Artisan::call('knowledge:import', ['source' => 'bible', '--no-embeddings' => true]);

    expect($status)->toBe(Command::SUCCESS);

    $record = KnowledgeDocumentRecord::query()->where('reference', 'John 3:16')->firstOrFail();

    expect($record->source_type)->toBe(SourceType::BibleVerse->value)
        ->and($record->metadata['source_identifier'])->toBe('bible.test_fixture')
        ->and($record->metadata['copyright_status'])->toBe('verified')
        ->and($record->metadata['license'])->toBe('Test fixture only');
});

it('detects missing provenance inventory and blocks import', function (): void {
    $directory = provenanceTestImportDirectory('provenance-missing');
    provenanceBibleFixture($directory);
    config()->set('knowledge.import.directories', [$directory]);
    config()->set('knowledge_sources.sources', []);

    $status = Artisan::call('knowledge:import', ['source' => 'bible', '--no-embeddings' => true]);
    $output = Artisan::output();

    expect($status)->toBe(Command::FAILURE)
        ->and($output)->toContain('No unambiguous source inventory entry is configured')
        ->and(KnowledgeDocumentRecord::query()->count())->toBe(0);
});

it('does not approve missing license information automatically', function (): void {
    $directory = provenanceTestImportDirectory('provenance-no-license');
    provenanceBibleFixture($directory);
    config()->set('knowledge.import.directories', [$directory]);
    configureSourceInventory(approvedBibleSource([
        'license' => null,
    ]));

    $status = Artisan::call('knowledge:verify');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('License information is missing; no license has been inferred.');
});

it('blocks sources that require verification by default', function (): void {
    $directory = provenanceTestImportDirectory('provenance-requires-verification');
    provenanceBibleFixture($directory);
    config()->set('knowledge.import.directories', [$directory]);
    configureSourceInventory(approvedBibleSource([
        'copyright_status' => 'requires_verification',
        'verification_status' => 'requires_verification',
        'import_allowed' => false,
        'license' => null,
    ]));

    $status = Artisan::call('knowledge:import', ['source' => 'bible', '--no-embeddings' => true]);
    $output = Artisan::output();

    expect($status)->toBe(Command::FAILURE)
        ->and($output)->toContain('requires_verification')
        ->and(KnowledgeDocumentRecord::query()->count())->toBe(0);
});

it('blocks restricted and unknown copyright statuses', function (string $copyrightStatus): void {
    $directory = provenanceTestImportDirectory('provenance-'.$copyrightStatus);
    provenanceBibleFixture($directory);
    config()->set('knowledge.import.directories', [$directory]);
    configureSourceInventory(approvedBibleSource([
        'copyright_status' => $copyrightStatus,
        'verification_status' => 'approved',
        'import_allowed' => true,
    ]));

    $status = Artisan::call('knowledge:import', ['source' => 'bible', '--no-embeddings' => true]);
    $output = Artisan::output();

    expect($status)->toBe(Command::FAILURE)
        ->and($output)->toContain("copyright status [{$copyrightStatus}] blocks import")
        ->and(KnowledgeDocumentRecord::query()->count())->toBe(0);
})->with(['restricted', 'unknown']);

it('returns machine readable json verification output', function (): void {
    $directory = provenanceTestImportDirectory('provenance-json');
    provenanceBibleFixture($directory);
    config()->set('knowledge.import.directories', [$directory]);
    configureSourceInventory(approvedBibleSource());

    $status = Artisan::call('knowledge:verify', ['--format' => 'json']);
    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($status)->toBe(Command::SUCCESS)
        ->and($payload['overall_status'])->toBe('passed')
        ->and($payload['counts']['approved'])->toBe(1)
        ->and($payload['counts']['blocked'])->toBe(0)
        ->and($payload['results'][0]['source']['id'])->toBe('bible.test_fixture')
        ->and($payload['results'][0]['import_allowed'])->toBeTrue();
});

it('fails clearly for invalid provenance configuration', function (): void {
    config()->set('knowledge_sources.sources', [[
        'id' => 'bad-source',
        'type' => 'bible',
        'name' => 'Bad Source',
        'copyright_status' => 'not-a-status',
        'verification_status' => 'approved',
        'import_allowed' => true,
    ]]);

    Artisan::call('knowledge:verify');
})->throws(InvalidArgumentException::class, 'Invalid copyright status [not-a-status]');
