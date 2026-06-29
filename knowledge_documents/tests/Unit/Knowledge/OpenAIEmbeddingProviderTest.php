<?php

declare(strict_types=1);

use App\Infrastructure\Knowledge\Embedding\OpenAIEmbeddingProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

it('requests embeddings from OpenAI using configured model and retries failures', function (): void {
    config()->set('services.openai.api_key', 'test-key');
    config()->set('services.openai.embedding_model', 'configured-embedding-model');
    config()->set('services.openai.embedding_dimensions', 3);
    config()->set('services.openai.retry_attempts', 2);
    config()->set('services.openai.retry_sleep_ms', 0);

    Http::fakeSequence()
        ->push(['error' => ['message' => 'temporary failure']], 500)
        ->push([
            'data' => [
                ['embedding' => [0.1, 0.2, 0.3]],
                ['embedding' => [0.4, 0.5, 0.6]],
            ],
        ]);

    $provider = app(OpenAIEmbeddingProvider::class);

    $embeddings = $provider->embedMany(['first text', 'second text']);

    expect($embeddings)->toBe([
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ]);

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'configured-embedding-model'
            && $request['dimensions'] === 3
            && $request['input'] === ['first text', 'second text'];
    });
});

it('requires an OpenAI API key', function (): void {
    config()->set('services.openai.api_key', '');
    config()->set('services.openai.embedding_model', 'configured-embedding-model');

    app(OpenAIEmbeddingProvider::class)->embed('text');
})->throws(RuntimeException::class, 'OPENAI_API_KEY is not configured.');
