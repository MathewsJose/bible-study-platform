<?php

declare(strict_types=1);

use App\Application\Knowledge\Importing\Services\BibleCorpusAuditService;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

function bibleReadinessDirectory(string $name): string
{
    $directory = storage_path("app/{$name}");

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory;
}

/**
 * @param  array<string, mixed>  $payload
 */
function writeBibleReadinessFile(string $directory, string $file, array $payload): string
{
    $path = "{$directory}/{$file}";
    file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

    return $path;
}

it('audits split bible source files without importing documents', function (): void {
    $directory = bibleReadinessDirectory('bible-readiness-split');
    $genesis = writeBibleReadinessFile($directory, 'genesis.json', [
        'translation' => 'synthetic',
        'language' => 'en',
        'source_url' => 'https://example.test/genesis',
        'license' => 'Test fixture only',
        'license_url' => 'https://example.test/license',
        'source_edition' => 'Synthetic Fixture',
        'book' => 'Genesis',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning God created heaven and earth.'],
        ],
    ]);
    $john = writeBibleReadinessFile($directory, 'john.json', [
        'translation' => 'synthetic',
        'language' => 'en',
        'source_url' => 'https://example.test/john',
        'license' => 'Test fixture only',
        'license_url' => 'https://example.test/license',
        'source_edition' => 'Synthetic Fixture',
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning was the Word, and the Word was with God.'],
        ],
    ]);

    $status = Artisan::call('knowledge:bible-audit', [
        '--path' => [$genesis, $john],
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($status)->toBe(Command::FAILURE)
        ->and($payload['summary']['files'])->toBe(2)
        ->and($payload['summary']['books_found'])->toBe(2)
        ->and($payload['summary']['verses_found'])->toBe(2)
        ->and($payload['books_found'])->toContain('Genesis', 'John')
        ->and($payload['books_missing'])->toContain('Tobit')
        ->and($payload['import_ready'])->toBeFalse()
        ->and(KnowledgeDocumentRecord::query()->count())->toBe(0);
});

it('reports catholic canon gaps unexpected books invalid references and source identity gaps', function (): void {
    $directory = bibleReadinessDirectory('bible-readiness-invalid');
    $path = writeBibleReadinessFile($directory, 'invalid.json', [
        'book' => 'Made Up',
        'chapter' => 0,
        'verses' => [
            ['verse' => 1, 'text' => 'Duplicate first verse.'],
            ['verse' => 1, 'text' => 'Duplicate second verse.'],
            ['verse' => 0, 'text' => ''],
        ],
    ]);

    $result = app(BibleCorpusAuditService::class)->audit([$path]);

    expect($result['books_unexpected'])->toContain('Made Up')
        ->and($result['deuterocanonical']['missing'])->toContain('Tobit', 'Judith', 'Wisdom', 'Sirach', 'Baruch', '1 Maccabees', '2 Maccabees')
        ->and($result['duplicate_references_within_source'])->toBe(['Made Up 0:1'])
        ->and($result['invalid_chapters'])->toBe(['Made Up 0'])
        ->and($result['invalid_verses'])->toBe(['Made Up 0:0'])
        ->and($result['empty_verses'])->toBe(['Made Up 0:0'])
        ->and($result['source_identity_warnings'])->toContain(
            'invalid.json: translation is missing.',
            'invalid.json: language is missing.',
            'invalid.json: license_url is missing.',
        );
});

it('distinguishes legitimate duplicate references across source names', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'Translation A',
        'reference' => 'John 1:1',
    ]);
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'Translation B',
        'reference' => 'John 1:1',
    ]);

    $status = Artisan::call('knowledge:duplicates', [
        '--source-type' => SourceType::BibleVerse->value,
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($status)->toBe(Command::SUCCESS)
        ->and($payload['summary']['within_source_duplicates'])->toBe(0)
        ->and($payload['summary']['across_source_duplicates'])->toBe(1)
        ->and($payload['across_source_duplicates'][0]['reference'])->toBe('John 1:1')
        ->and($payload['across_source_duplicates'][0]['source_names'])->toBe(['Translation A', 'Translation B']);
});

it('does not allow force to bypass the provenance gate', function (): void {
    $directory = bibleReadinessDirectory('bible-readiness-blocked');
    writeBibleReadinessFile($directory, 'john.json', [
        'translation' => 'blocked-source',
        'language' => 'en',
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'In the beginning was the Word.'],
        ],
    ]);
    config()->set('knowledge.import.directories', [$directory]);
    config()->set('knowledge_sources.sources', [[
        'id' => 'bible.blocked',
        'type' => 'bible',
        'name' => 'Blocked Bible Fixture',
        'copyright_status' => 'requires_verification',
        'verification_status' => 'requires_verification',
        'import_allowed' => false,
    ]]);

    $status = Artisan::call('knowledge:import', [
        'source' => 'bible',
        '--force' => true,
        '--no-embeddings' => true,
    ]);

    expect($status)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('requires_verification')
        ->and(KnowledgeDocumentRecord::query()->count())->toBe(0);
});

it('supports resumable imports and leaves embeddings untouched for unchanged documents', function (): void {
    $directory = bibleReadinessDirectory('bible-readiness-resume');
    $john = writeBibleReadinessFile($directory, 'john.json', [
        'translation' => 'synthetic-resume',
        'language' => 'en',
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'Original verse text.'],
        ],
    ]);

    config()->set('knowledge.import.directories', [$directory]);

    expect(Artisan::call('knowledge:import', [
        'source' => 'bible',
        '--no-embeddings' => true,
    ]))->toBe(Command::SUCCESS);

    KnowledgeDocumentRecord::query()
        ->where('reference', 'John 1:1')
        ->update([
            'embedding_status' => EmbeddingStatus::Ready->value,
            'embedding' => json_encode([0.1, 0.2, 0.3], JSON_THROW_ON_ERROR),
            'embedding_model' => 'synthetic-model',
            'embedding_provider' => 'synthetic-provider',
            'embedding_dimensions' => 3,
            'embedded_at' => now(),
        ]);

    expect(Artisan::call('knowledge:import', [
        'source' => 'bible',
        '--no-embeddings' => true,
    ]))->toBe(Command::SUCCESS);

    $unchanged = KnowledgeDocumentRecord::query()->where('reference', 'John 1:1')->firstOrFail();

    expect($unchanged->embedding_status)->toBe(EmbeddingStatus::Ready)
        ->and($unchanged->embedding_model)->toBe('synthetic-model');

    writeBibleReadinessFile($directory, 'genesis.json', [
        'translation' => 'synthetic-resume',
        'language' => 'en',
        'book' => 'Genesis',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'A newly available split source file.'],
        ],
    ]);
    file_put_contents($john, json_encode([
        'translation' => 'synthetic-resume',
        'language' => 'en',
        'book' => 'John',
        'chapter' => 1,
        'verses' => [
            ['verse' => 1, 'text' => 'Changed verse text.'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect(Artisan::call('knowledge:import', [
        'source' => 'bible',
        '--force' => true,
        '--no-embeddings' => true,
    ]))->toBe(Command::SUCCESS);

    $changed = KnowledgeDocumentRecord::query()->where('reference', 'John 1:1')->firstOrFail();

    expect(KnowledgeDocumentRecord::query()->where('source_type', SourceType::BibleVerse->value)->count())->toBe(2)
        ->and($changed->content)->toBe('Changed verse text.')
        ->and($changed->embedding_status)->toBe(EmbeddingStatus::Pending)
        ->and($changed->embedding)->toBeNull();
});
