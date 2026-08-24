<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\DTOs;

use App\Domain\Knowledge\Enums\CopyrightStatus;

final readonly class SourceProvenance
{
    public function __construct(
        public string $sourceIdentifier,
        public string $sourceType,
        public string $sourceName,
        public string $language,
        public CopyrightStatus $copyrightStatus,
        public ?string $sourceVersion = null,
        public ?string $author = null,
        public ?string $title = null,
        public ?string $work = null,
        public ?string $reference = null,
        public ?string $edition = null,
        public ?string $publication = null,
        public ?string $license = null,
        public ?string $sourceUrl = null,
        public ?string $licenseUrl = null,
        public ?string $rightsNotes = null,
        public ?string $importedAt = null,
        public ?string $checksum = null,
        public ?string $contentChecksum = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        return array_filter([
            'source_identifier' => $this->sourceIdentifier,
            'source_version' => $this->sourceVersion,
            'source_type' => $this->sourceType,
            'source_name' => $this->sourceName,
            'author' => $this->author,
            'title' => $this->title,
            'work' => $this->work,
            'reference' => $this->reference,
            'language' => $this->language,
            'edition' => $this->edition,
            'publication' => $this->publication,
            'license' => $this->license,
            'copyright_status' => $this->copyrightStatus->value,
            'source_url' => $this->sourceUrl,
            'license_url' => $this->licenseUrl,
            'rights_notes' => $this->rightsNotes,
            'imported_at' => $this->importedAt,
            'checksum' => $this->checksum,
            'content_checksum' => $this->contentChecksum,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
