<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Infrastructure\Knowledge\Embedding\LocalEmbeddingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

it('requests local embeddings in batches and preserves response order', function (): void {
    Http::preventStrayRequests();

    config()->set('embeddings.local.url', 'http://embedding-service:8000');
    config()->set('embeddings.model', 'sentence-transformers/all-MiniLM-L6-v2');
    config()->set('embeddings.dimensions', 3);

    Http::fake([
        'http://embedding-service:8000/embed' => Http::response([
            'embeddings' => [
                [0.1, 0.2, 0.3],
                [0.4, 0.5, 0.6],
            ],
            'model' => 'sentence-transformers/all-MiniLM-L6-v2',
            'dimensions' => 3,
        ]),
    ]);

    $embeddings = app(LocalEmbeddingProvider::class)->embedMany(['Bible verse', 'Catechism paragraph']);

    expect($embeddings)->toBe([
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://embedding-service:8000/embed'
        && $request['texts'] === ['Bible verse', 'Catechism paragraph']);
});

it('requires a configured local embedding URL', function (): void {
    config()->set('embeddings.local.url', '');
    config()->set('embeddings.model', 'sentence-transformers/all-MiniLM-L6-v2');

    app(LocalEmbeddingProvider::class)->embed('text');
})->throws(RuntimeException::class, 'LOCAL_EMBEDDING_URL is not configured.');

it('rejects invalid local embedding responses', function (): void {
    config()->set('embeddings.local.url', 'http://embedding-service:8000');
    config()->set('embeddings.model', 'sentence-transformers/all-MiniLM-L6-v2');
    config()->set('embeddings.dimensions', 3);

    Http::fake([
        'http://embedding-service:8000/embed' => Http::response(['embeddings' => []]),
    ]);

    app(LocalEmbeddingProvider::class)->embed('text');
})->throws(RuntimeException::class);

it('rejects local embedding dimension mismatches', function (): void {
    config()->set('embeddings.local.url', 'http://embedding-service:8000');
    config()->set('embeddings.model', 'sentence-transformers/all-MiniLM-L6-v2');
    config()->set('embeddings.dimensions', 3);

    Http::fake([
        'http://embedding-service:8000/embed' => Http::response([
            'embeddings' => [[0.1, 0.2]],
            'model' => 'sentence-transformers/all-MiniLM-L6-v2',
            'dimensions' => 2,
        ]),
    ]);

    app(LocalEmbeddingProvider::class)->embed('text');
})->throws(RuntimeException::class, 'expected 3');

it('surfaces local service errors', function (): void {
    config()->set('embeddings.local.url', 'http://embedding-service:8000');
    config()->set('embeddings.model', 'sentence-transformers/all-MiniLM-L6-v2');
    config()->set('embeddings.dimensions', 3);

    Http::fake([
        'http://embedding-service:8000/embed' => Http::response(['detail' => 'model failed'], 503),
    ]);

    app(LocalEmbeddingProvider::class)->embed('text');
})->throws(\Illuminate\Http\Client\RequestException::class);

it('surfaces local service timeouts', function (): void {
    config()->set('embeddings.local.url', 'http://embedding-service:8000');
    config()->set('embeddings.model', 'sentence-transformers/all-MiniLM-L6-v2');
    config()->set('embeddings.dimensions', 3);

    Http::fake([
        'http://embedding-service:8000/embed' => fn (): never => throw new ConnectionException('timeout'),
    ]);

    app(LocalEmbeddingProvider::class)->embed('text');
})->throws(ConnectionException::class);

it('selects the local provider from the service container', function (): void {
    config()->set('embeddings.provider', 'local');

    expect(app(EmbeddingProviderInterface::class))->toBeInstanceOf(LocalEmbeddingProvider::class);
});
