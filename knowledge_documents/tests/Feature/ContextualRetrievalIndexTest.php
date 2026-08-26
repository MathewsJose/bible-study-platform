<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Retrieval\Experiments\ContextualIndexSearchService;
use App\Application\Knowledge\Retrieval\Experiments\ContextualRetrievalIndexService;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Infrastructure\Knowledge\Persistence\RetrievalContextualDocumentRecord;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

final class ContextualIndexFakeEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return $this->vector($text);
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text): array => $this->vector($text), $texts);
    }

    public function identifier(): string
    {
        return 'contextual-index-test';
    }

    /**
     * @return list<float>
     */
    private function vector(string $text): array
    {
        $lower = strtolower($text);

        return [
            str_contains($lower, 'word') || str_contains($lower, 'beginning') ? 1.0 : 0.1,
            str_contains($lower, 'god') ? 1.0 : 0.1,
            str_contains($lower, 'life') ? 1.0 : 0.1,
        ];
    }
}

beforeEach(function (): void {
    config()->set('embeddings.dimensions', 3);
    app()->instance(EmbeddingProviderInterface::class, new ContextualIndexFakeEmbeddingProvider());
});

it('builds verse and neighboring contextual windows without losing citations', function (): void {
    seedJohnChapter();

    $result = app(ContextualRetrievalIndexService::class)->build([
        'window' => 'plus_minus_1',
        'batch' => 2,
    ]);

    $johnOneTwo = RetrievalContextualDocumentRecord::query()->where('reference', 'John 1:2')->firstOrFail();

    expect($result['created'])->toBe(4)
        ->and($johnOneTwo->source_name)->toBe('Douay-Rheims Bible')
        ->and($johnOneTwo->book)->toBe('John')
        ->and($johnOneTwo->chapter)->toBe(1)
        ->and($johnOneTwo->verse)->toBe(2)
        ->and($johnOneTwo->context_window)->toBe('plus_minus_1')
        ->and($johnOneTwo->context_text)->toContain('Context John 1:1')
        ->and($johnOneTwo->context_text)->toContain('Target John 1:2')
        ->and($johnOneTwo->context_text)->toContain('Context John 1:3');
});

it('is checksum-idempotent and resumable', function (): void {
    seedJohnChapter();
    $service = app(ContextualRetrievalIndexService::class);

    $first = $service->build(['window' => 'verse']);
    $second = $service->build(['window' => 'verse']);
    $forced = $service->build(['window' => 'verse', 'force' => true]);

    expect($first['created'])->toBe(4)
        ->and($second['skipped'])->toBe(4)
        ->and($forced['updated'])->toBe(4)
        ->and(RetrievalContextualDocumentRecord::query()->where('context_window', 'verse')->count())->toBe(4);
});

it('builds plus-minus-three and chapter contextual documents', function (): void {
    seedJohnChapter();
    $service = app(ContextualRetrievalIndexService::class);

    $service->build(['window' => 'plus_minus_3']);
    $service->build(['window' => 'chapter']);

    $windowThree = RetrievalContextualDocumentRecord::query()
        ->where('reference', 'John 1:1')
        ->where('context_window', 'plus_minus_3')
        ->firstOrFail();
    $chapter = RetrievalContextualDocumentRecord::query()
        ->where('reference', 'John 1:1')
        ->where('context_window', 'chapter')
        ->firstOrFail();

    expect($windowThree->context_text)->toContain('Context John 1:4')
        ->and($chapter->context_text)->toContain('Chapter context John 1')
        ->and($chapter->context_checksum)->toBeString()
        ->and(strlen($chapter->context_checksum))->toBe(64);
});

it('generates contextual embeddings without changing source documents', function (): void {
    seedJohnChapter();
    app(ContextualRetrievalIndexService::class)->build(['window' => 'verse']);

    pendingArtisan('retrieval:contextual-embeddings', [
        '--window' => 'verse',
        '--batch' => 2,
        '--format' => 'json',
    ])->assertSuccessful();

    $record = RetrievalContextualDocumentRecord::query()->where('reference', 'John 1:1')->firstOrFail();
    $source = KnowledgeDocumentRecord::query()->where('reference', 'John 1:1')->firstOrFail();

    expect($record->embedding_model)->toBe('contextual-index-test')
        ->and($record->embedding_dimensions)->toBe(3)
        ->and($record->embedded_at)->not->toBeNull()
        ->and($source->embedding)->toBeNull();
});

it('searches the isolated contextual index and preserves source document ids', function (): void {
    seedJohnChapter();
    app(ContextualRetrievalIndexService::class)->build(['window' => 'verse']);

    pendingArtisan('retrieval:contextual-embeddings', [
        '--window' => 'verse',
        '--format' => 'json',
    ])->assertSuccessful();

    $results = app(ContextualIndexSearchService::class)->search('In the beginning was the Word and God', 'verse', 3);

    expect($results)->not->toBeEmpty()
        ->and($results[0]['reference'])->toBe('John 1:1')
        ->and($results[0]['source_name'])->toBe('Douay-Rheims Bible')
        ->and($results[0]['context_window'])->toBe('verse');
});

it('exposes json command reports for indexing and blocked benchmark state', function (): void {
    seedJohnChapter();

    pendingArtisan('retrieval:contextual-index', [
        '--window' => 'verse',
        '--dry-run' => true,
        '--format' => 'json',
    ])->assertSuccessful()
        ->expectsOutputToContain('"created": 4');

    pendingArtisan('evaluate:contextual-index', [
        '--window' => 'verse',
        '--limit' => 1,
        '--format' => 'json',
    ])->assertSuccessful()
        ->expectsOutputToContain('"decision": "BLOCKED"');
});

function seedJohnChapter(): void
{
    foreach ([
        1 => 'In the beginning was the Word, and the Word was with God, and the Word was God.',
        2 => 'The same was in the beginning with God.',
        3 => 'All things were made by him: and without him was made nothing that was made.',
        4 => 'In him was life, and the life was the light of men.',
    ] as $verse => $content) {
        KnowledgeDocumentRecord::factory()->create([
            'source_type' => 'bible_verse',
            'source_name' => 'Douay-Rheims Bible',
            'tradition' => 'catholic',
            'reference' => 'John 1:'.$verse,
            'title' => 'John 1:'.$verse,
            'content' => $content,
            'metadata' => ['book' => 'John', 'chapter' => 1, 'verse' => $verse],
            'embedding' => null,
        ]);
    }

    KnowledgeDocumentRecord::factory()->create([
        'source_type' => 'bible_chapter',
        'source_name' => 'Douay-Rheims Bible',
        'tradition' => 'catholic',
        'reference' => 'John 1',
        'title' => 'John 1',
        'content' => 'In the beginning was the Word. The same was in the beginning with God.',
        'metadata' => ['book' => 'John', 'chapter' => 1],
        'embedding' => null,
    ]);
}

/**
 * @param  array<string, mixed>  $parameters
 */
function pendingArtisan(string $command, array $parameters = []): PendingCommand
{
    $pendingCommand = artisan($command, $parameters);

    if (! $pendingCommand instanceof PendingCommand) {
        throw new RuntimeException('Expected a pending Artisan command during the feature test.');
    }

    return $pendingCommand;
}
