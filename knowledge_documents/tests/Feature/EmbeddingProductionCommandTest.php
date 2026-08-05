<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Infrastructure\Knowledge\Embedding\OpenAIEmbeddingProvider;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Http\Client\RequestException;

final class ProductionCommandFakeEmbeddingProvider implements EmbeddingProviderInterface
{
    public int $calls = 0;

    public bool $fail = false;

    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0];
    }

    public function embedMany(array $texts): array
    {
        $this->calls++;

        if ($this->fail) {
            throw new RuntimeException('temporary provider failure');
        }

        return array_map(static fn (): array => [0.1, 0.2, 0.3], $texts);
    }

    public function identifier(): string
    {
        return 'text-embedding-3-small';
    }
}

function bindProductionEmbeddingProvider(?ProductionCommandFakeEmbeddingProvider $provider = null): ProductionCommandFakeEmbeddingProvider
{
    config()->set('embeddings.provider', 'openai');
    config()->set('embeddings.model', 'text-embedding-3-small');
    config()->set('embeddings.dimensions', 3);
    config()->set('embeddings.queue_connection', 'sync');
    config()->set('embeddings.batch_size', 2);
    config()->set('embeddings.openai.api_key', 'test-key');

    $provider ??= new ProductionCommandFakeEmbeddingProvider();
    app()->instance(EmbeddingProviderInterface::class, $provider);

    return $provider;
}

it('checks embedding provider health without exposing the API key', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'embedding' => [0.1, 0.2, 0.3],
        'embedding_status' => EmbeddingStatus::Ready,
        'embedding_model' => 'text-embedding-3-small',
    ]);

    bindProductionEmbeddingProvider();

    $this->artisan('embeddings:health')
        ->expectsOutputToContain('Embedding Provider Health')
        ->expectsOutputToContain('Provider: openai')
        ->expectsOutputToContain('Model: text-embedding-3-small')
        ->expectsOutputToContain('Dimensions: 3')
        ->expectsOutputToContain('API Key: configured')
        ->expectsOutputToContain('API Connection: OK')
        ->doesntExpectOutputToContain('test-key')
        ->assertSuccessful();
});

it('checks local embedding provider health without requiring an API key', function (): void {
    KnowledgeDocumentRecord::factory()->create([
        'embedding' => [0.1, 0.2, 0.3],
        'embedding_status' => EmbeddingStatus::Ready,
        'embedding_provider' => 'local',
        'embedding_model' => 'sentence-transformers/all-MiniLM-L6-v2',
        'embedding_dimensions' => 3,
    ]);

    config()->set('embeddings.provider', 'local');
    config()->set('embeddings.model', 'sentence-transformers/all-MiniLM-L6-v2');
    config()->set('embeddings.dimensions', 3);
    config()->set('embeddings.openai.api_key', '');

    app()->instance(EmbeddingProviderInterface::class, new ProductionCommandFakeEmbeddingProvider());

    $this->artisan('embeddings:health')
        ->expectsOutputToContain('Provider: local')
        ->expectsOutputToContain('API Key: not required')
        ->expectsOutputToContain('API Connection: OK')
        ->expectsOutputToContain('Model loaded: YES')
        ->expectsOutputToContain('sentence-transformers/all-MiniLM-L6-v2')
        ->assertSuccessful();
});


it('reports missing OpenAI API key during health checks', function (): void {
    config()->set('embeddings.provider', 'openai');
    config()->set('embeddings.model', 'text-embedding-3-small');
    config()->set('embeddings.dimensions', 3);
    config()->set('embeddings.openai.api_key', '');

    app()->instance(EmbeddingProviderInterface::class, app(OpenAIEmbeddingProvider::class));

    $this->artisan('embeddings:health')
        ->expectsOutputToContain('API Key: missing')
        ->expectsOutputToContain('API Connection: FAILED')
        ->doesntExpectOutputToContain('sk-')
        ->assertFailed();
});

it('dry-runs embedding generation without calling the provider', function (): void {
    $provider = bindProductionEmbeddingProvider();
    KnowledgeDocumentRecord::factory()->count(3)->create();

    $this->artisan('embeddings:generate', ['--force' => true, '--limit' => 2, '--dry-run' => true])
        ->expectsOutputToContain('Provider: openai')
        ->expectsOutputToContain('Model: text-embedding-3-small')
        ->expectsOutputToContain('Dimensions: 3')
        ->expectsOutputToContain('Dry run: 2 knowledge documents would be processed.')
        ->expectsOutputToContain('No embedding API request was made')
        ->expectsOutputToContain('Skipped: 2')
        ->assertSuccessful();

    expect($provider->calls)->toBe(0)
        ->and(KnowledgeDocumentRecord::query()->whereNotNull('embedding')->count())->toBe(0);
});

it('regenerates a limited number of embeddings with model metadata', function (): void {
    bindProductionEmbeddingProvider();

    KnowledgeDocumentRecord::factory()->count(3)->create([
        'embedding' => [0.9, 0.9, 0.9],
        'embedding_status' => EmbeddingStatus::Ready,
        'embedding_model' => 'dummy-model',
    ]);

    $this->artisan('embeddings:generate', ['--force' => true, '--limit' => 2])
        ->expectsOutputToContain('Processed: 2')
        ->expectsOutputToContain('Successful: 2')
        ->expectsOutputToContain('Failed: 0')
        ->assertSuccessful();

    expect(KnowledgeDocumentRecord::query()->where('embedding_model', 'text-embedding-3-small')->count())->toBe(2)
        ->and(KnowledgeDocumentRecord::query()->where('embedding_provider', 'openai')->count())->toBe(2)
        ->and(KnowledgeDocumentRecord::query()->where('embedding_dimensions', 3)->count())->toBe(2)
        ->and(KnowledgeDocumentRecord::query()->where('embedding_model', 'dummy-model')->count())->toBe(1);
});

it('is idempotent when force regenerates existing embeddings', function (): void {
    bindProductionEmbeddingProvider();

    KnowledgeDocumentRecord::factory()->count(2)->create([
        'embedding' => [0.9, 0.9, 0.9],
        'embedding_status' => EmbeddingStatus::Ready,
        'embedding_model' => 'dummy-model',
    ]);

    $this->artisan('embeddings:generate', ['--force' => true])->assertSuccessful();
    $this->artisan('embeddings:generate', ['--force' => true])->assertSuccessful();

    expect(KnowledgeDocumentRecord::query()->count())->toBe(2)
        ->and(KnowledgeDocumentRecord::query()->where('embedding_model', 'text-embedding-3-small')->count())->toBe(2)
        ->and(KnowledgeDocumentRecord::query()->where('embedding_provider', 'openai')->count())->toBe(2)
        ->and(KnowledgeDocumentRecord::query()->where('embedding_dimensions', 3)->count())->toBe(2);
});

it('marks documents failed when the provider request fails', function (): void {
    $provider = bindProductionEmbeddingProvider();
    $provider->fail = true;

    $document = KnowledgeDocumentRecord::factory()->create();

    $this->artisan('embeddings:generate', ['--force' => true, '--limit' => 1])
        ->expectsOutputToContain('Failed: 1')
        ->assertFailed();

    $document->refresh();

    expect($document->embedding_status)->toBe(EmbeddingStatus::Failed)
        ->and($document->embedding_error)->toContain('temporary provider failure');
});
