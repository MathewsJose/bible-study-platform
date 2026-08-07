<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importing;

use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;

final class ChurchFathersKnowledgeImporter extends AbstractFileKnowledgeImporter
{
    public function identifier(): string
    {
        return 'church_fathers';
    }

    public function displayName(): string
    {
        return 'Church Fathers';
    }

    public function supports(string $path): bool
    {
        $lowerPath = strtolower(basename($path));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['json', 'txt', 'md'], true)) {
            return false;
        }

        if (str_contains($lowerPath, 'church-father') || str_contains($lowerPath, 'church_father') || str_contains($lowerPath, 'fathers')) {
            return true;
        }

        if ($extension !== 'json') {
            return false;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) && isset($payload['sections']) && (isset($payload['author']) || isset($payload['work']));
    }

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult
    {
        if (strtolower(pathinfo($rawDocument->path, PATHINFO_EXTENSION)) !== 'json') {
            return parent::validate($rawDocument);
        }

        try {
            $payload = $rawDocument->jsonPayload();
        } catch (\JsonException $exception) {
            return ValidationResult::invalid(['Church Fathers import JSON is invalid: '.$exception->getMessage()]);
        }

        if (($payload['sections'] ?? []) === [] || ! is_array($payload['sections'] ?? null)) {
            return ValidationResult::invalid(['Church Fathers payload must contain at least one section.']);
        }

        return ValidationResult::valid();
    }

    public function normalize(RawKnowledgeDocument $rawDocument): array
    {
        if (strtolower(pathinfo($rawDocument->path, PATHINFO_EXTENSION)) !== 'json') {
            return $this->normalizeTextSegments($rawDocument, SourceType::ChurchFather->value, basename($rawDocument->path));
        }

        $payload = $rawDocument->jsonPayload();
        $author = (string) ($payload['author'] ?? 'Unknown Author');
        $work = (string) ($payload['work'] ?? 'Unknown Work');
        $sourceName = "{$author}, {$work}";
        $language = (string) ($payload['language'] ?? $this->language($rawDocument));
        $documents = [];

        foreach ($payload['sections'] as $section) {
            $title = (string) ($section['title'] ?? 'Untitled');
            $reference = (string) ($section['reference'] ?? $title);
            $content = trim((string) ($section['content'] ?? ''));

            $documents[] = new NormalizedKnowledgeDocument(
                sourceType: SourceType::ChurchFather->value,
                sourceName: $sourceName,
                tradition: Tradition::Catholic->value,
                reference: $reference,
                title: $title,
                content: $content,
                language: $language,
                checksum: hash('sha256', $content),
                metadata: $this->provenance($rawDocument, [
                    'author' => $author,
                    'work' => $work,
                    'section' => $section['section'] ?? $reference,
                    'century' => $payload['century'] ?? null,
                    'original_language' => $payload['original_language'] ?? null,
                    'translator' => $payload['translator'] ?? null,
                    'source_url' => $payload['source_url'] ?? null,
                ]),
            );
        }

        return $documents;
    }
}
