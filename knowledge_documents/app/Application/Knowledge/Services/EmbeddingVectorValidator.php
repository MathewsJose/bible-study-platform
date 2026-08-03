<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Exceptions\InvalidEmbeddingVectorException;

final readonly class EmbeddingVectorValidator
{
    /**
     * @param  list<float>  $vector
     */
    public function validate(array $vector): void
    {
        $expectedDimensions = (int) config('embeddings.dimensions', 1536);
        $actualDimensions = count($vector);

        if ($actualDimensions !== $expectedDimensions) {
            throw InvalidEmbeddingVectorException::forDimensions($expectedDimensions, $actualDimensions);
        }

        foreach ($vector as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidEmbeddingVectorException('Embedding vector contains a non-finite value.');
            }
        }
    }
}
