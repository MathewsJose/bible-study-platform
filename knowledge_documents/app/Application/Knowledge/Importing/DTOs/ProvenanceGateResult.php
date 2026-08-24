<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\DTOs;

final readonly class ProvenanceGateResult
{
    /**
     * @param  list<string>  $warnings
     * @param  list<string>  $errors
     */
    public function __construct(
        public bool $allowed,
        public ?SourceInventoryItem $source,
        public ?SourceProvenance $provenance,
        public array $warnings = [],
        public array $errors = [],
        public bool $overrideUsed = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'override_used' => $this->overrideUsed,
            'source' => $this->source?->toArray(),
            'provenance' => $this->provenance?->toMetadata(),
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
}
