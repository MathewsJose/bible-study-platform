<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Exceptions;

use RuntimeException;

final class InvalidEmbeddingVectorException extends RuntimeException
{
    public static function forDimensions(int $expected, int $actual): self
    {
        return new self("Embedding vector has {$actual} dimensions; expected {$expected}.");
    }
}
