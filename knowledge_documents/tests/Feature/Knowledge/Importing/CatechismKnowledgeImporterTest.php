<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Importing\CatechismKnowledgeImporter;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\assertDatabaseHas;

final class SprintTenRecordingEmbeddingProvider implements EmbeddingProviderInterface
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
        return 'sprint-ten-test-model';
    }
}

it('normalizes ccc paragraphs with hierarchy metadata and official references', function (): void {
    $path = storage_path('app/sprint10-ccc.json');
    file_put_contents($path, json_encode([
        'catechism' => 'Catechism of the Catholic Church',
        'language' => 'en',
        'source_edition' => 'Second Edition',
        'publication_year' => 1997,
        'paragraphs' => [
            [
                'number' => 456,
                'title' => 'Why did the Word become flesh?',
                'part' => 'Part I',
                'section' => 'Section One',
                'chapter' => 'Chapter Two',
                'article' => 'Article 3',
                'paragraph' => 'Paragraph 1',
                'category' => 'christology',
                'topics' => ['incarnation', 'salvation'],
                'content' => 'With the Nicene Creed, we answer by confessing CCC 457 and John 1:14. See Philippians 2:6-11.',
                'church_father_references' => ['St. Athanasius, De Incarnatione'],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $importer = app(CatechismKnowledgeImporter::class);
        $documents = $importer->normalize($importer->fetch($path));

        expect($documents)->toHaveCount(1)
            ->and($documents[0]->reference)->toBe('CCC 456')
            ->and($documents[0]->metadata)
            ->toMatchArray([
                'document_type' => 'catechism_paragraph',
                'reference_number' => 456,
                'paragraph_number' => 456,
                'category' => 'christology',
                'topics' => ['incarnation', 'salvation'],
                'part' => 'Part I',
                'section' => 'Section One',
                'chapter' => 'Chapter Two',
                'article' => 'Article 3',
                'paragraph' => 'Paragraph 1',
                'source_edition' => 'Second Edition',
                'publication_year' => 1997,
                'tradition' => 'catholic',
                'internal_references' => ['CCC 457'],
                'scripture_references' => ['John 1:14', 'Philippians 2:6-11'],
                'church_father_references' => ['St. Athanasius, De Incarnatione'],
            ]);
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('rejects invalid ccc payloads with clear diagnostics', function (): void {
    $path = storage_path('app/sprint10-invalid-ccc.json');
    file_put_contents($path, json_encode([
        'paragraphs' => [
            ['number' => 232, 'section' => 'Section One', 'content' => 'Valid text.', 'ccc_references' => ['not a ccc reference']],
            ['number' => 232, 'content' => 'Duplicate.'],
            ['number' => 0, 'content' => '', 'topics' => 'trinity'],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $validation = app(CatechismKnowledgeImporter::class)->validate(app(CatechismKnowledgeImporter::class)->fetch($path));
        $errors = implode(' ', $validation->errors);

        expect($validation->valid)->toBeFalse()
            ->and($errors)->toContain('Duplicate Catechism paragraph [CCC 232]')
            ->and($errors)->toContain('Broken Catechism hierarchy for [CCC 232]')
            ->and($errors)->toContain('Invalid Catechism paragraph number [0]')
            ->and($errors)->toContain('Missing Catechism content for [index 2]')
            ->and($errors)->toContain('Malformed topics metadata for [index 2]')
            ->and($errors)->toContain('Invalid Catechism reference [not a ccc reference]');
    } finally {
        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('imports ccc paragraphs incrementally and queues embeddings only for changed paragraphs', function (): void {
    config()->set('embeddings.dimensions', 3);
    config()->set('embeddings.queue_connection', 'sync');

    $provider = new SprintTenRecordingEmbeddingProvider();
    app()->instance(EmbeddingProviderInterface::class, $provider);

    $directory = storage_path('app/sprint10-incremental-ccc');
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $path = $directory.'/ccc-sample.json';
    file_put_contents($path, json_encode([
        'catechism' => 'Catechism of the Catholic Church',
        'paragraphs' => [
            ['number' => 232, 'part' => 'Part I', 'content' => 'Original trinity paragraph.'],
        ],
    ], JSON_THROW_ON_ERROR));

    config()->set('knowledge.import.directories', [$directory]);

    expect(Artisan::call('knowledge:import', ['source' => 'catechism', '--force' => true]))
        ->toBe(Command::SUCCESS);
    expect($provider->batchSizes)->toBe([1]);

    file_put_contents($path, json_encode([
        'catechism' => 'Catechism of the Catholic Church',
        'paragraphs' => [
            ['number' => 232, 'part' => 'Part I', 'content' => 'Changed trinity paragraph.'],
        ],
    ], JSON_THROW_ON_ERROR));

    expect(Artisan::call('knowledge:import', ['source' => 'catechism', '--force' => true]))
        ->toBe(Command::SUCCESS);

    expect($provider->batchSizes)->toBe([1, 1])
        ->and(KnowledgeDocumentRecord::query()->where('reference', 'CCC 232')->first()?->content)->toBe('Changed trinity paragraph.')
        ->and(KnowledgeDocumentRecord::query()->where('reference', 'CCC 232')->first()?->embedding_status)->toBe(EmbeddingStatus::Ready);

    assertDatabaseHas('knowledge_documents', [
        'source_type' => SourceType::Catechism->value,
        'reference' => 'CCC 232',
    ]);
});

it('reports catechism diagnostics in knowledge status', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => 'Catechism of the Catholic Church',
        'reference' => 'CCC 456',
        'metadata' => [
            'part' => 'Part I',
            'section' => 'Section One',
            'article' => 'Article 3',
            'internal_references' => ['CCC 457'],
            'scripture_references' => ['John 1:14'],
        ],
    ]);

    $status = Artisan::call('knowledge:status');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($output)->toContain('Catechism Import Status')
        ->and($output)->toContain('Total CCC paragraphs: 1')
        ->and($output)->toContain('Parts: 1')
        ->and($output)->toContain('Sections: 1')
        ->and($output)->toContain('Articles: 1')
        ->and($output)->toContain('Cross references: 1')
        ->and($output)->toContain('Scripture references: 1');
});
