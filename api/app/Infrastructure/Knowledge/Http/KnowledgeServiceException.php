<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Http;

use RuntimeException;

final class KnowledgeServiceException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 503,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
