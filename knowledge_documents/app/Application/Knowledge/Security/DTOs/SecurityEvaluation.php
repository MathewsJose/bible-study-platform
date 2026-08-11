<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\DTOs;

use App\Application\Knowledge\Security\Enums\DataClassification;

final readonly class SecurityEvaluation
{
    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $allowed,
        public string $status,
        public string $errorCode,
        public string $message,
        public string $safeInput,
        public DataClassification $classification,
        public PiiScanResult $pii,
        public PromptInjectionResult $promptInjection,
        public array $warnings = [],
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function diagnostics(): array
    {
        return [
            'allowed' => $this->allowed,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'classification' => $this->classification->value,
            'pii' => $this->pii->toSafeArray(),
            'prompt_injection' => $this->promptInjection->toSafeArray(),
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
        ];
    }
}
