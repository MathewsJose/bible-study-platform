<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Contracts;

use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;

interface KnowledgeImporterInterface
{
    public function identifier(): string;

    public function displayName(): string;

    public function version(): string;

    /**
     * @return list<string>
     */
    public function supportedLanguages(): array;

    /**
     * @return array{license: string|null, license_url: string|null, rights_notes: string|null}
     */
    public function licensing(): array;

    public function supports(string $path): bool;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function fetch(string $path, array $metadata = []): RawKnowledgeDocument;

    /**
     * @return list<\App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument>
     */
    public function normalize(RawKnowledgeDocument $rawDocument): array;

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult;
}
