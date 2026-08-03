<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Embedding;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use RuntimeException;

final class NullEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        throw new RuntimeException('No embedding provider is configured.');
    }

    public function embedMany(array $texts): array
    {
        throw new RuntimeException('No embedding provider is configured.');
    }

    public function identifier(): string
    {
        return 'null';
    }
}
