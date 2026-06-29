<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\assertDatabaseHas;

final class RecordingEmbeddingProvider implements EmbeddingProviderInterface
{
    /** @var list<int> */
    public array $batchSizes = [];

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        $this->batchSizes[] = count($texts);

        return array_map(
            static fn (string $text): array => [(float) mb_strlen($text), 0.0, 1.0],
            $texts,
        );
    }
}

it('generates embeddings for pending knowledge documents in batches of 100', function (): void {
    $provider = new RecordingEmbeddingProvider();
    app()->instance(EmbeddingProviderInterface::class, $provider);

    for ($index = 1; $index <= 101; $index++) {
        KnowledgeDocumentRecord::factory()->create([
            'reference' => "CCC {$index}",
            'content' => "Paragraph {$index}",
        ]);
    }

    KnowledgeDocumentRecord::factory()->create([
        'reference' => 'CCC 999',
        'content' => 'Already embedded',
        'embedding' => json_encode([0.1, 0.2, 0.3], JSON_THROW_ON_ERROR),
    ]);

    $status = Artisan::call('embeddings:generate');
    $output = Artisan::output();

    expect($status)->toBe(Command::SUCCESS)
        ->and($provider->batchSizes)->toBe([100, 1])
        ->and($output)->toContain('documents processed: 101')
        ->and($output)->toContain('embeddings generated: 101')
        ->and($output)->toContain('failures: 0');

    assertDatabaseHas('knowledge_documents', [
        'reference' => 'CCC 1',
        'embedding' => json_encode([11.0, 0.0, 1.0], JSON_THROW_ON_ERROR),
    ]);
});

it('reports when there are no pending embeddings', function (): void {
    $status = Artisan::call('embeddings:generate');

    expect($status)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('No knowledge documents need embeddings.');
});
