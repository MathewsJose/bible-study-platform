<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Embedding;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use RuntimeException;

final readonly class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private HttpFactory $http) {}

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $embeddings = $this->embedMany([$text]);

        return $embeddings[0] ?? throw new RuntimeException('OpenAI embedding response did not include an embedding.');
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     *
     * @throws RequestException
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $apiKey = (string) config('services.openai.api_key');
        $model = (string) config('services.openai.embedding_model');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        if ($model === '') {
            throw new RuntimeException('OpenAI embedding model is not configured.');
        }

        $payload = [
            'model' => $model,
            'input' => $texts,
        ];

        $dimensions = config('services.openai.embedding_dimensions');

        if (is_int($dimensions)) {
            $payload['dimensions'] = $dimensions;
        }

        $response = $this->http
            ->retry(
                (int) config('services.openai.retry_attempts', 3),
                (int) config('services.openai.retry_sleep_ms', 200),
            )
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post((string) config('services.openai.embeddings_url'), $payload)
            ->throw()
            ->json('data');

        if (! is_array($response)) {
            throw new RuntimeException('OpenAI embedding response did not include data.');
        }

        $embeddings = [];

        foreach ($response as $item) {
            $embedding = Arr::get($item, 'embedding');

            if (! is_array($embedding)) {
                throw new RuntimeException('OpenAI embedding response contained an invalid embedding.');
            }

            $embeddings[] = array_map(static fn (mixed $value): float => (float) $value, array_values($embedding));
        }

        if (count($embeddings) !== count($texts)) {
            throw new RuntimeException('OpenAI embedding response count did not match the request count.');
        }

        return $embeddings;
    }
}
