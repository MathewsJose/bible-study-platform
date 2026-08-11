<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\DTOs;

use App\Application\Knowledge\Security\Enums\DataClassification;

final readonly class PiiScanResult
{
    /**
     * @param  list<PiiDetection>  $detections
     */
    public function __construct(
        public string $redactedText,
        public array $detections,
        public DataClassification $classification,
    ) {}

    public function detected(): bool
    {
        return $this->detections !== [];
    }

    /** @return array<string, mixed> */
    public function toSafeArray(): array
    {
        return [
            'detected' => $this->detected(),
            'classification' => $this->classification->value,
            'detections' => array_map(static fn (PiiDetection $detection): array => $detection->toArray(), $this->detections),
        ];
    }
}
