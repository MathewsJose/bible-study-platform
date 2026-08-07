<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Importing\ChurchFathersKnowledgeImporter;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\assertDatabaseHas;

final class SprintElevenRecordingEmbeddingProvider implements EmbeddingProviderInterface
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
        return 'sprint-eleven-test-model';
    }
}

it('normalizes church father sections with authorship provenance and explicit references', function (): void {
    $path = storage_path('app/sprint11-augustine.json');
    file_put_contents($path, json_encode([
        'author' => 'St. Augustine',
        'work' => 'Tractates on John',
        'volume' => 'NPNF1-07',
        'century' => '4th-5th',
        'language' => 'en',
        'original_language' => 'Latin',
        'translation' => 'Public domain translation',
        'source_edition' => 'Nicene and Post-Nicene Fathers',
        'sections' => [
            [
                'title' => 'Tractate 2',
                'reference' => 'Augustine, Tractates on John, Tractate 2',
                'section' => 'Tractate 2',
                'chapter' => 'John 1',
                'paragraph' => '2',
                'topics' => ['logos', 'incarnation'],
                'content' => 'The Evangelist says John 1:1 and John 1:14. See also CCC 456.',
                'church_father_references' => ['Athanasius, On the Incarnation, Chapter 8'],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $documents = app(ChurchFathersKnowledgeImporter::class)->normalize(app(ChurchFathersKnowledgeImporter::class)->fetch($path));

        expect($documents)->toHaveCount(1)
            ->and($documents[0]->reference)->toBe('Augustine, Tractates on John, Tractate 2')
            ->and($documents[0]->metadata)
            ->toMatchArray([
                'author' => 'St. Augustine',
                'author_key' => 'augustine',
                'work' => 'Tractates on John',
                'volume' => 'NPNF1-07',
                'section' => 'Tractate 2',
                'chapter' => 'John 1',
                'paragraph' => '2',
                'language' => 'en',
                'original_language' => 'Latin',
                'translation' => 'Public domain translation',
                'century' => '4th-5th',
                'topics' => ['logos', 'incarnation'],
                'source_edition' => 'Nicene and Post-Nicene Fathers',
                'tradition' => 'catholic',
                'import_version' => '1.0.0',
                'scripture_references' => ['John 1:1', 'John 1:14'],
                'catechism_references' => ['CCC 456'],
                'church_father_references' => ['Athanasius, On the Incarnation, Chapter 8'],
            ])
            ->and($documents[0]->metadata['cross_references'])
            ->toBe(['John 1:1', 'John 1:14', 'CCC 456', 'Athanasius, On the Incarnation, Chapter 8']);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('rejects invalid church fathers payloads with detailed diagnostics', function (): void {
    $path = storage_path('app/sprint11-invalid-fathers.json');
    file_put_contents($path, json_encode([
        'author' => '',
        'work' => '',
        'sections' => [
            ['reference' => 'Duplicate Ref', 'content' => 'Text.', 'catechism_references' => ['bad ccc']],
            ['reference' => 'Duplicate Ref', 'content' => 'Text.'],
            ['reference' => '', 'content' => '', 'paragraph' => '1'],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $validation = app(ChurchFathersKnowledgeImporter::class)->validate(app(ChurchFathersKnowledgeImporter::class)->fetch($path));
        $errors = implode(' ', $validation->errors);

        expect($validation->valid)->toBeFalse()
            ->and($errors)->toContain('missing author')
            ->and($errors)->toContain('missing work')
            ->and($errors)->toContain('Duplicate Church Fathers reference [Duplicate Ref]')
            ->and($errors)->toContain('Missing canonical reference')
            ->and($errors)->toContain('Missing Church Fathers content')
            ->and($errors)->toContain('Invalid Church Fathers hierarchy')
            ->and($errors)->toContain('Invalid Catechism reference [bad ccc]');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('imports church fathers with dashed source alias and author filter', function (): void {
    $directory = storage_path('app/sprint11-filtered-fathers');
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($directory.'/church-fathers-augustine.json', json_encode([
        'author' => 'St. Augustine',
        'work' => 'Tractates on John',
        'sections' => [
            ['title' => 'Tractate 2', 'reference' => 'Augustine, Tractates on John, Tractate 2', 'content' => 'John 1:14 is proclaimed.'],
        ],
    ], JSON_THROW_ON_ERROR));

    file_put_contents($directory.'/church-fathers-athanasius.json', json_encode([
        'author' => 'St. Athanasius',
        'work' => 'On the Incarnation',
        'sections' => [
            ['title' => 'Chapter 8', 'reference' => 'Athanasius, On the Incarnation, Chapter 8', 'content' => 'The Word became flesh.'],
        ],
    ], JSON_THROW_ON_ERROR));

    config()->set('knowledge.import.directories', [$directory]);

    $status = Artisan::call('knowledge:import', [
        'source' => 'church-fathers',
        '--author' => 'augustine',
        '--no-embeddings' => true,
    ]);

    expect($status)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('imported: 1');

    assertDatabaseHas('knowledge_documents', [
        'source_type' => SourceType::ChurchFather->value,
        'source_name' => 'St. Augustine, Tractates on John',
        'reference' => 'Augustine, Tractates on John, Tractate 2',
    ]);

    expect(KnowledgeDocumentRecord::query()->where('reference', 'Athanasius, On the Incarnation, Chapter 8')->exists())->toBeFalse();
});

it('imports changed church father documents incrementally and queues embeddings', function (): void {
    config()->set('embeddings.dimensions', 3);
    config()->set('embeddings.queue_connection', 'sync');

    $provider = new SprintElevenRecordingEmbeddingProvider();
    app()->instance(EmbeddingProviderInterface::class, $provider);

    $directory = storage_path('app/sprint11-incremental-fathers');
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $path = $directory.'/church-fathers-chrysostom.json';
    file_put_contents($path, json_encode([
        'author' => 'St. John Chrysostom',
        'work' => 'Homilies on Romans',
        'sections' => [
            ['title' => 'Homily 9', 'reference' => 'Chrysostom, Homilies on Romans, Homily 9', 'content' => 'Original Romans 5:1 comment.'],
        ],
    ], JSON_THROW_ON_ERROR));

    config()->set('knowledge.import.directories', [$directory]);

    expect(Artisan::call('knowledge:import', ['source' => 'church_fathers', '--force' => true]))
        ->toBe(Command::SUCCESS);
    expect($provider->batchSizes)->toBe([1]);

    file_put_contents($path, json_encode([
        'author' => 'St. John Chrysostom',
        'work' => 'Homilies on Romans',
        'sections' => [
            ['title' => 'Homily 9', 'reference' => 'Chrysostom, Homilies on Romans, Homily 9', 'content' => 'Changed Romans 5:1 comment.'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect(Artisan::call('knowledge:import', ['source' => 'church_fathers', '--force' => true]))
        ->toBe(Command::SUCCESS);

    expect($provider->batchSizes)->toBe([1, 1])
        ->and(KnowledgeDocumentRecord::query()->where('reference', 'Chrysostom, Homilies on Romans, Homily 9')->first()?->content)
        ->toBe('Changed Romans 5:1 comment.')
        ->and(KnowledgeDocumentRecord::query()->where('reference', 'Chrysostom, Homilies on Romans, Homily 9')->first()?->embedding_status)
        ->toBe(EmbeddingStatus::Ready);
});

it('reports church fathers diagnostics in knowledge status', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::ChurchFather->value,
        'source_name' => 'St. Gregory the Great, Moralia in Job',
        'reference' => 'Gregory the Great, Moralia in Job, Book 1',
        'metadata' => [
            'author' => 'St. Gregory the Great',
            'work' => 'Moralia in Job',
            'scripture_references' => ['Job 1:1'],
            'cross_references' => ['Job 1:1', 'CCC 309'],
        ],
    ]);

    $status = Artisan::call('knowledge:status');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Church Fathers Import Status')
        ->and($output)->toContain('Authors imported: 1')
        ->and($output)->toContain('Works imported: 1')
        ->and($output)->toContain('Documents: 1')
        ->and($output)->toContain('Scripture references: 1')
        ->and($output)->toContain('Cross references: 2');
});
