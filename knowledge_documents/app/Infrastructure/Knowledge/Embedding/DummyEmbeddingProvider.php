<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Embedding;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;

final class DummyEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        $hash = hash('sha256', mb_strtolower(trim($text)), true);
        $values = [];
        $dimensions = (int) config('embeddings.dimensions', 1536);

        for ($index = 0; $index < $dimensions; $index++) {
            $byte = ord($hash[$index % strlen($hash)]);
            $values[] = round(($byte / 127.5) - 1.0, 6);
        }

        return $values;
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    public function identifier(): string
    {
        return 'dummy-model';
    }
}
