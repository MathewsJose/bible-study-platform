<?php

declare(strict_types=1);

use App\Application\Knowledge\Importing\Services\DouayRheimsBibleStagingService;
use App\Infrastructure\Knowledge\Importing\BibleCanon;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

function douayStageDirectory(string $name): string
{
    $directory = storage_path("app/testing/{$name}");

    if (is_dir($directory)) {
        File::deleteDirectory($directory);
    }

    File::ensureDirectoryExists($directory);

    return $directory;
}

/**
 * @return array<string, string>
 */
function douaySourceBookMap(): array
{
    $reflection = new ReflectionClass(DouayRheimsBibleStagingService::class);

    /** @var array<string, string> $map */
    $map = $reflection->getConstant('SOURCE_BOOKS');

    return $map;
}

/**
 * @param  list<string>  $omitBooks
 */
function writeDouaySourceFixture(string $sourceDirectory, array $omitBooks = [], bool $includeExtra = false, bool $includeTobitPrologue = false): void
{
    $rawDirectory = "{$sourceDirectory}/bible/raw";
    File::ensureDirectoryExists($rawDirectory);

    foreach (app(BibleCanon::class)->books() as $book) {
        if (in_array($book, $omitBooks, true)) {
            continue;
        }

        $slug = douaySourceBookMap()[$book];
        $chapters = [
            [
                'chapter' => 1,
                'summary' => "Synthetic {$book} chapter summary.",
                'verses' => [
                    [
                        'verse' => 1,
                        'text' => "Synthetic Original Douay-Rheims staging verse for {$book}.",
                        'cross_refs' => [
                            ['text' => 'John 1.'],
                        ],
                    ],
                ],
            ],
        ];

        if ($book === 'Tobit' && $includeTobitPrologue) {
            array_unshift($chapters, [
                'chapter' => 0,
                'summary' => 'Synthetic source prologue that must not become a Bible chapter.',
                'verses' => [
                    ['verse' => 1, 'text' => 'Synthetic prologue text.'],
                ],
            ]);
        }

        File::put("{$rawDirectory}/{$slug}.json", json_encode([
            'book' => $slug,
            'book_title' => "The Book of {$book}",
            'short_title' => $book,
            'chapters' => $chapters,
        ], JSON_THROW_ON_ERROR));
    }

    if ($includeExtra) {
        File::put("{$rawDirectory}/3-esdras.json", json_encode([
            'book' => '3-esdras',
            'chapters' => [
                [
                    'chapter' => 1,
                    'verses' => [
                        ['verse' => 1, 'text' => 'Synthetic extra source book excluded from canonical staging.'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));
    }
}

it('stages a complete 73 book Douay-Rheims source fixture without importing', function (): void {
    $root = douayStageDirectory('douay-complete');
    $source = "{$root}/original/repo";
    $output = "{$root}/staging";
    writeDouaySourceFixture($source, includeExtra: true, includeTobitPrologue: true);
    $documentsBefore = KnowledgeDocumentRecord::query()->count();

    $status = Artisan::call('knowledge:bible-stage-douay-rheims', [
        '--source' => $source,
        '--output' => $output,
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    $manifest = $payload['manifest'];
    $auditSummary = $payload['audit_summary'];

    expect($status)->toBe(Command::SUCCESS)
        ->and($manifest['staged_books'])->toBe(73)
        ->and($manifest['technical_validation_ready'])->toBeTrue()
        ->and($manifest['import_readiness'])->toBeFalse()
        ->and($manifest['import_allowed'])->toBeFalse()
        ->and($manifest['verification_status'])->toBe('requires_verification')
        ->and($manifest['decision'])->toBe('BLOCKED - PROVENANCE')
        ->and($manifest['source_unchanged_during_staging'])->toBeTrue()
        ->and($manifest['extra_source_books_excluded'])->toContain('3-esdras')
        ->and($manifest['excluded_source_records'])->toBe([[
            'book' => 'Tobit',
            'chapter' => 0,
            'reason' => 'Non-canonical source prologue or introductory chapter was excluded from normalized Bible chapter data.',
        ]])
        ->and($auditSummary['books_found'])->toBe(73)
        ->and($payload['deuterocanonical']['found'])->toBe(['Tobit', 'Judith', 'Wisdom', 'Sirach', 'Baruch', '1 Maccabees', '2 Maccabees'])
        ->and(File::files("{$output}/normalized"))->toHaveCount(73)
        ->and(File::exists("{$output}/manifest/source-manifest.json"))->toBeTrue()
        ->and(File::exists("{$output}/reports/validation-report.json"))->toBeTrue()
        ->and(KnowledgeDocumentRecord::query()->count())->toBe($documentsBefore);
});

it('blocks staging readiness when a deuterocanonical source file is missing', function (): void {
    $root = douayStageDirectory('douay-missing-deuterocanonical');
    $source = "{$root}/original/repo";
    $output = "{$root}/staging";
    writeDouaySourceFixture($source, omitBooks: ['Tobit']);

    $status = Artisan::call('knowledge:bible-stage-douay-rheims', [
        '--source' => $source,
        '--output' => $output,
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($status)->toBe(Command::SUCCESS)
        ->and($payload['manifest']['technical_validation_ready'])->toBeFalse()
        ->and($payload['manifest']['decision'])->toBe('BLOCKED - CORPUS')
        ->and($payload['manifest']['missing_source_files'])->toBe(['Tobit'])
        ->and($payload['deuterocanonical']['missing'])->toContain('Tobit')
        ->and($payload['audit_summary']['books_found'])->toBe(72)
        ->and(KnowledgeDocumentRecord::query()->count())->toBe(0);
});

it('excludes staged source files from default import verification discovery', function (): void {
    $root = douayStageDirectory('douay-default-discovery');
    $staging = "{$root}/staging";
    File::ensureDirectoryExists($staging);
    File::put("{$staging}/invalid.json", json_encode([
        'book' => 'not-a-canonical-book',
        'chapter' => 0,
        'verses' => [
            ['verse' => 0, 'text' => ''],
        ],
    ], JSON_THROW_ON_ERROR));

    config()->set('knowledge.import.directories', [$root]);
    config()->set('knowledge.import.excluded_directories', [$staging]);

    $status = Artisan::call('knowledge:verify', [
        '--format' => 'json',
    ]);
    $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($status)->toBe(Command::SUCCESS)
        ->and($payload['counts']['failed_files'])->toBe(0)
        ->and($payload['counts']['unsupported_files'])->toBe(0)
        ->and(Artisan::output())->not->toContain('not-a-canonical-book');
});
