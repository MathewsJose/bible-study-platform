<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Embedding;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use RuntimeException;

final readonly class LocalEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private HttpFactory $http) {}

    /** @return list<float> */
    public function embed(string $text): array
    {
        $embeddings = $this->embedMany([$text]);

        return $embeddings[0] ?? throw new RuntimeException('Local embedding response did not include an embedding.');
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $url = rtrim((string) config('embeddings.local.url'), '/');
        $model = (string) config('embeddings.model');

        if ($url === '') {
            throw new RuntimeException('LOCAL_EMBEDDING_URL is not configured.');
        }

        if ($model === '') {
            throw new RuntimeException('Local embedding model is not configured.');
        }

        $response = $this->http
            ->retry(
                (int) config('embeddings.retry_attempts', 3),
                fn (int $attempt): int => max(1, (int) config('embeddings.retry_sleep_ms', 200)) * (2 ** max(0, $attempt - 1)),
            )
            ->timeout((int) config('embeddings.timeout', 30))
            ->connectTimeout(min(10, (int) config('embeddings.timeout', 30)))
            ->acceptJson()
            ->asJson()
            ->post($url.'/embed', [
                'texts' => $texts,
            ])
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('Local embedding response was invalid.');
        }

        $responseModel = Arr::get($response, 'model');
        if ($responseModel !== $model) {
            throw new RuntimeException("Local embedding service returned model [{$responseModel}], expected [{$model}].");
        }

        $responseDimensions = (int) Arr::get($response, 'dimensions', 0);
        $configuredDimensions = (int) config('embeddings.dimensions', 384);
        if ($responseDimensions !== $configuredDimensions) {
            throw new RuntimeException("Local embedding service returned {$responseDimensions} dimensions, expected {$configuredDimensions}.");
        }

        $items = Arr::get($response, 'embeddings');
        if (! is_array($items)) {
            throw new RuntimeException('Local embedding response did not include embeddings.');
        }

        if (count($items) !== count($texts)) {
            throw new RuntimeException('Local embedding response count did not match the request count.');
        }

        $embeddings = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('Local embedding response contained an invalid embedding.');
            }

            $embeddings[] = array_map(static fn (mixed $value): float => (float) $value, array_values($item));
        }

        return $embeddings;
    }

    public function identifier(): string
    {
        return (string) config('embeddings.model');
    }
}
