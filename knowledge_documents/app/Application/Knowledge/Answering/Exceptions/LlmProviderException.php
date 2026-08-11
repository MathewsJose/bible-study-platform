<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Exceptions;

use RuntimeException;
use Throwable;

class LlmProviderException extends RuntimeException
{
    /** @param array<string, mixed> $diagnostics */
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?string $model = null,
        public readonly array $diagnostics = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
