<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class ValidationResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public bool $valid,
        public array $warnings = [],
    ) {}
}
