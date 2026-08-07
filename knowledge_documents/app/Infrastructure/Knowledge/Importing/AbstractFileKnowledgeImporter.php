<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importing;

use App\Application\Knowledge\Importing\Contracts\DocumentNormalizerInterface;
use App\Application\Knowledge\Importing\Contracts\ImportValidatorInterface;
use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;
use App\Domain\Knowledge\Enums\Tradition;

abstract class AbstractFileKnowledgeImporter implements KnowledgeImporterInterface, DocumentNormalizerInterface, ImportValidatorInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function fetch(string $path, array $metadata = []): RawKnowledgeDocument
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read import file [{$path}].");
        }

        return new RawKnowledgeDocument(
            sourceIdentifier: $this->identifier(),
            path: $path,
            checksum: hash('sha256', $contents),
            contents: $contents,
            metadata: array_merge($this->licensing(), $metadata),
        );
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function supportedLanguages(): array
    {
        return ['en'];
    }

    public function licensing(): array
    {
        return [
            'license' => null,
            'license_url' => null,
            'rights_notes' => null,
        ];
    }

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult
    {
        if (trim($rawDocument->contents) === '') {
            return ValidationResult::invalid(['Import file is empty.']);
        }

        return ValidationResult::valid();
    }

    /**
     * @return list<NormalizedKnowledgeDocument>
     */
    protected function normalizeTextSegments(RawKnowledgeDocument $rawDocument, string $sourceType, string $sourceName): array
    {
        $segments = preg_split('/\n\s*\n/', trim($rawDocument->contents)) ?: [];
        $documents = [];
        $language = $this->language($rawDocument);

        foreach ($segments as $index => $segment) {
            $content = trim($segment);

            if ($content === '') {
                continue;
            }

            $reference = basename($rawDocument->path).'#'.($index + 1);
            $documents[] = new NormalizedKnowledgeDocument(
                sourceType: $sourceType,
                sourceName: $sourceName,
                tradition: Tradition::Catholic->value,
                reference: $reference,
                title: $reference,
                content: $content,
                language: $language,
                checksum: hash('sha256', $content),
                metadata: $this->provenance($rawDocument, [
                    'source_file' => basename($rawDocument->path),
                    'segment' => $index + 1,
                ]),
            );
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function provenance(RawKnowledgeDocument $rawDocument, array $metadata = []): array
    {
        return array_filter(array_merge($metadata, [
            'source_identifier' => $this->identifier(),
            'source_version' => $this->version(),
            'source_path' => basename($rawDocument->path),
            'source_url' => $rawDocument->metadata['source_url'] ?? null,
            'imported_at' => date(DATE_ATOM, filemtime($rawDocument->path) ?: time()),
            'source_checksum' => $rawDocument->checksum,
            'license' => $rawDocument->metadata['license'] ?? $this->licensing()['license'],
            'license_url' => $rawDocument->metadata['license_url'] ?? $this->licensing()['license_url'],
            'rights_notes' => $rawDocument->metadata['rights_notes'] ?? $this->licensing()['rights_notes'],
        ]), static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function language(RawKnowledgeDocument $rawDocument): string
    {
        return (string) ($rawDocument->metadata['language'] ?? $this->supportedLanguages()[0]);
    }
}
