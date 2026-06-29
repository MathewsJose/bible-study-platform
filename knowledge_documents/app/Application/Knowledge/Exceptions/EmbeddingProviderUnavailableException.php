<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Exceptions;

use RuntimeException;
use Throwable;

final class EmbeddingProviderUnavailableException extends RuntimeException
{
    public static function forSearch(Throwable $previous): self
    {
        return new self('Semantic search embeddings are unavailable.', previous: $previous);
    }
}
