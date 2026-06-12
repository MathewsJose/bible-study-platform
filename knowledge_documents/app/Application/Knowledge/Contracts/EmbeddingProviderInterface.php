<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;
}
