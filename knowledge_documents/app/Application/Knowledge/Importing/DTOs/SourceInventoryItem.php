<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\DTOs;

use App\Domain\Knowledge\Enums\CopyrightStatus;
use App\Domain\Knowledge\Enums\SourceInventoryStatus;
use InvalidArgumentException;

final readonly class SourceInventoryItem
{
    /**
     * @param  list<string>  $expectedReferences
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $language,
        public CopyrightStatus $copyrightStatus,
        public SourceInventoryStatus $verificationStatus,
        public bool $importAllowed,
        public ?string $author = null,
        public ?string $work = null,
        public ?string $title = null,
        public ?string $edition = null,
        public ?string $sourceVersion = null,
        public ?string $sourceUrl = null,
        public ?string $licenseUrl = null,
        public ?string $license = null,
        public ?string $rightsNotes = null,
        public ?int $expectedDocumentCount = null,
        public array $expectedReferences = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = trim((string) ($data['id'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($id === '' || $type === '' || $name === '') {
            throw new InvalidArgumentException('Source inventory entries require id, type, and name.');
        }

        $copyrightStatus = CopyrightStatus::tryFrom((string) ($data['copyright_status'] ?? CopyrightStatus::RequiresVerification->value));
        if (! $copyrightStatus instanceof CopyrightStatus) {
            throw new InvalidArgumentException("Invalid copyright status [{$data['copyright_status']}] for source [{$id}].");
        }

        $verificationStatus = SourceInventoryStatus::tryFrom((string) ($data['verification_status'] ?? SourceInventoryStatus::RequiresVerification->value));
        if (! $verificationStatus instanceof SourceInventoryStatus) {
            throw new InvalidArgumentException("Invalid verification status [{$data['verification_status']}] for source [{$id}].");
        }

        return new self(
            id: $id,
            type: $type,
            name: $name,
            language: (string) ($data['language'] ?? 'en'),
            copyrightStatus: $copyrightStatus,
            verificationStatus: $verificationStatus,
            importAllowed: (bool) ($data['import_allowed'] ?? false),
            author: self::optionalString($data['author'] ?? null),
            work: self::optionalString($data['work'] ?? null),
            title: self::optionalString($data['title'] ?? null),
            edition: self::optionalString($data['edition'] ?? null),
            sourceVersion: self::optionalString($data['source_version'] ?? $data['version'] ?? null),
            sourceUrl: self::optionalString($data['source_url'] ?? null),
            licenseUrl: self::optionalString($data['license_url'] ?? null),
            license: self::optionalString($data['license'] ?? null),
            rightsNotes: self::optionalString($data['rights_notes'] ?? null),
            expectedDocumentCount: isset($data['expected_document_count']) ? (int) $data['expected_document_count'] : null,
            expectedReferences: array_values(array_filter(array_map(
                static fn (mixed $reference): string => trim((string) $reference),
                (array) ($data['expected_references'] ?? []),
            ), static fn (string $reference): bool => $reference !== '')),
        );
    }

    public function provenance(string $importerVersion, array $metadata = []): SourceProvenance
    {
        return new SourceProvenance(
            sourceIdentifier: $this->id,
            sourceType: $this->type,
            sourceName: $this->name,
            language: (string) ($metadata['language'] ?? $this->language),
            copyrightStatus: $this->copyrightStatus,
            sourceVersion: (string) ($metadata['source_version'] ?? $this->sourceVersion ?? $importerVersion),
            author: (string) ($metadata['author'] ?? $this->author ?? ''),
            title: (string) ($metadata['title'] ?? $this->title ?? ''),
            work: (string) ($metadata['work'] ?? $this->work ?? ''),
            edition: (string) ($metadata['edition'] ?? $metadata['source_edition'] ?? $this->edition ?? ''),
            license: (string) ($metadata['license'] ?? $this->license ?? ''),
            sourceUrl: (string) ($metadata['source_url'] ?? $this->sourceUrl ?? ''),
            licenseUrl: (string) ($metadata['license_url'] ?? $this->licenseUrl ?? ''),
            rightsNotes: (string) ($metadata['rights_notes'] ?? $this->rightsNotes ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'author' => $this->author,
            'work' => $this->work,
            'title' => $this->title,
            'language' => $this->language,
            'edition' => $this->edition,
            'source_version' => $this->sourceVersion,
            'source_url' => $this->sourceUrl,
            'license_url' => $this->licenseUrl,
            'license' => $this->license,
            'copyright_status' => $this->copyrightStatus->value,
            'verification_status' => $this->verificationStatus->value,
            'rights_notes' => $this->rightsNotes,
            'expected_document_count' => $this->expectedDocumentCount,
            'expected_references' => $this->expectedReferences,
            'import_allowed' => $this->importAllowed,
        ];
    }

    private static function optionalString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
