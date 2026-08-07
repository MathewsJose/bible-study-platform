<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Importing\BibleKnowledgeImporter;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\assertDatabaseHas;

final class SprintNineRecordingEmbeddingProvider implements EmbeddingProviderInterface
{
    /** @var list<int> */
    public array $batchSizes = [];

    public function embed(string $text): array
    {
        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        $this->batchSizes[] = count($texts);

        return array_map(static fn (string $text): array => [(float) mb_strlen($text), 0.0, 1.0], $texts);
    }

    public function identifier(): string
    {
        return 'sprint-nine-test-model';
    }
}

it('normalizes full bible payloads into canonical verse and chapter documents', function (): void {
    $path = storage_path('app/sprint9-full-bible.json');
    file_put_contents($path, json_encode([
        'translation' => 'douay-rheims',
        'language' => 'en',
        'source_edition' => 'Public domain test edition',
        'books' => [
            [
                'book' => 'Genesis',
                'abbreviation' => 'Gen',
                'chapters' => [
                    [
                        'chapter' => 1,
                        'verses' => [
                            ['verse' => 1, 'text' => 'In the beginning God created heaven, and earth.'],
                            ['verse' => 2, 'text' => 'And the earth was void and empty.'],
                        ],
                    ],
                ],
            ],
            [
                'book' => 'John',
                'abbreviation' => 'Jn',
                'chapters' => [
                    [
                        'chapter' => 1,
                        'verses' => [
                            ['verse' => 14, 'text' => 'And the Word was made flesh.', 'cross_references' => ['John 1:1', 'Philippians 2:6-11']],
                        ],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $importer = app(BibleKnowledgeImporter::class);
        $raw = $importer->fetch($path);
        $documents = $importer->normalize($raw);

        expect($documents)->toHaveCount(5)
            ->and($documents[0]->reference)->toBe('Genesis 1:1')
            ->and($documents[0]->metadata['canonical_order'])->toBeLessThan($documents[3]->metadata['canonical_order'])
            ->and($documents[3]->reference)->toBe('John 1:14')
            ->and($documents[3]->metadata)
            ->toMatchArray([
                'book' => 'John',
                'book_abbreviation' => 'Jn',
                'chapter' => 1,
                'verse' => 14,
                'testament' => 'New Testament',
                'translation' => 'douay_rheims',
                'tradition' => 'catholic',
                'source_edition' => 'Public domain test edition',
                'import_version' => '1.0.0',
                'cross_references' => ['John 1:1', 'Philippians 2:6-11'],
            ])
            ->and($documents[4]->sourceType)->toBe(SourceType::BibleChapter->value)
            ->and($documents[4]->metadata['verses'])->toBe([
                [
                    'verse' => 14,
                    'reference' => 'John 1:14',
                    'text' => 'And the Word was made flesh.',
                    'cross_references' => ['John 1:1', 'Philippians 2:6-11'],
                ],
            ])
            ->and($documents[4]->metadata['cross_references'])->toBe(['John 1:1', 'Philippians 2:6-11']);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('rejects invalid bible payloads with meaningful diagnostics', function (): void {
    $path = storage_path('app/sprint9-invalid-bible.json');
    file_put_contents($path, json_encode([
        'book' => 'Made Up',
        'chapter' => 0,
        'verses' => [
            ['verse' => 1, 'text' => 'First'],
            ['verse' => 1, 'text' => 'Duplicate'],
            ['verse' => 0, 'text' => ''],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $importer = app(BibleKnowledgeImporter::class);
        $validation = $importer->validate($importer->fetch($path));

        expect($validation->valid)->toBeFalse()
            ->and(implode(' ', $validation->errors))
            ->toContain('Invalid Bible book [Made Up]')
            ->toContain('Invalid chapter number [0]')
            ->toContain('Duplicate Bible reference [Made Up 0:1]')
            ->toContain('Missing verse content for [Made Up 0:0]');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('imports bible documents with book chapter and translation filters', function (): void {
    $directory = storage_path('app/sprint9-filtered-bible');
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($directory.'/licensed-bible.json', json_encode([
        'translation' => 'public-domain-test',
        'books' => [
            ['book' => 'Genesis', 'chapters' => [['chapter' => 1, 'verses' => [['verse' => 1, 'text' => 'Genesis text.']]]]],
            ['book' => 'John', 'chapters' => [['chapter' => 1, 'verses' => [['verse' => 14, 'text' => 'John text.']]]]],
        ],
    ], JSON_THROW_ON_ERROR));

    config()->set('knowledge.import.directories', [$directory]);

    $status = Artisan::call('knowledge:import', [
        'source' => 'bible',
        '--book' => 'John',
        '--chapter' => 1,
        '--translation' => 'public-domain-test',
        '--no-embeddings' => true,
    ]);

    expect($status)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('imported: 2');

    assertDatabaseHas('knowledge_documents', [
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'Public Domain Test Bible',
        'reference' => 'John 1:14',
    ]);

    expect(KnowledgeDocumentRecord::query()->where('reference', 'Genesis 1:1')->exists())->toBeFalse();
});

it('updates only changed bible documents and queues embeddings for changed documents', function (): void {
    config()->set('embeddings.dimensions', 3);
    config()->set('embeddings.queue_connection', 'sync');

    $provider = new SprintNineRecordingEmbeddingProvider();
    app()->instance(EmbeddingProviderInterface::class, $provider);

    $directory = storage_path('app/sprint9-incremental-bible');
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $path = $directory.'/bible-john-1.json';
    file_put_contents($path, json_encode([
        'book' => 'John',
        'chapter' => 1,
        'translation' => 'public-domain-test',
        'verses' => [
            ['verse' => 1, 'text' => 'Original verse.'],
        ],
    ], JSON_THROW_ON_ERROR));

    config()->set('knowledge.import.directories', [$directory]);

    expect(Artisan::call('knowledge:import', ['source' => 'bible', '--force' => true]))
        ->toBe(Command::SUCCESS);
    expect($provider->batchSizes)->toBe([2]);

    file_put_contents($path, json_encode([
        'book' => 'John',
        'chapter' => 1,
        'translation' => 'public-domain-test',
        'verses' => [
            ['verse' => 1, 'text' => 'Changed verse.'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect(Artisan::call('knowledge:import', ['source' => 'bible', '--force' => true]))
        ->toBe(Command::SUCCESS);

    expect($provider->batchSizes)->toBe([2, 2])
        ->and(KnowledgeDocumentRecord::query()->where('reference', 'John 1:1')->first()?->content)->toBe('Changed verse.')
        ->and(KnowledgeDocumentRecord::query()->where('reference', 'John 1:1')->first()?->embedding_status)->toBe(EmbeddingStatus::Ready);
});

it('displays bible diagnostics in knowledge status', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => 'Public Domain Test Bible',
        'reference' => 'John 1:1',
        'metadata' => [
            'book' => 'John',
            'chapter' => 1,
            'verse' => 1,
            'testament' => 'New Testament',
            'translation' => 'public_domain_test',
        ],
    ]);

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleChapter->value,
        'source_name' => 'Public Domain Test Bible',
        'reference' => 'John 1',
        'metadata' => [
            'book' => 'John',
            'chapter' => 1,
            'testament' => 'New Testament',
            'translation' => 'public_domain_test',
        ],
    ]);

    $status = Artisan::call('knowledge:status');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Bible Import Status')
        ->and($output)->toContain('Books imported: 1')
        ->and($output)->toContain('New Testament count: 1')
        ->and($output)->toContain('Chapter count: 1')
        ->and($output)->toContain('Verse count: 1')
        ->and($output)->toContain('public_domain_test');
});
