<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Response;

final class AISecurityException extends Exception implements ShouldntReport
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = Response::HTTP_FORBIDDEN,
    ) {
        parent::__construct($message);
    }
}
