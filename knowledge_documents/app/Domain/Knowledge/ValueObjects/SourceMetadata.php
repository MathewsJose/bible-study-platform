<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

final class SourceMetadata implements Arrayable
{
    public function __construct(
        public readonly ?string $sourceUrl = null,
        public readonly ?string $license = null,
        public readonly ?string $licenseUrl = null,
        public readonly ?string $importedFrom = null,
        public readonly ?string $importedAt = null,
        public readonly ?string $rightsNotes = null,
        public readonly string $language = 'en',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'source_url' => $this->sourceUrl,
            'license' => $this->license,
            'license_url' => $this->licenseUrl,
            'imported_from' => $this->importedFrom,
            'imported_at' => $this->importedAt ?? now()->toIso8601String(),
            'rights_notes' => $this->rightsNotes,
            'language' => $this->language,
        ], fn($value) => $value !== null);
    }
}
