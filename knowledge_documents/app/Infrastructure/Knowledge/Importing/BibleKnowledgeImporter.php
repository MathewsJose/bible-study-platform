<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importing;

use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;

final class BibleKnowledgeImporter extends AbstractFileKnowledgeImporter
{
    public function identifier(): string
    {
        return 'bible';
    }

    public function displayName(): string
    {
        return 'Bible';
    }

    public function supports(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
            return false;
        }

        $lowerPath = strtolower(basename($path));

        if (str_contains($lowerPath, 'bible') || str_contains($lowerPath, 'douay')) {
            return true;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload)
            && isset($payload['book'], $payload['chapter'], $payload['verses'])
            && is_array($payload['verses']);
    }

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult
    {
        try {
            $payload = $rawDocument->jsonPayload();
        } catch (\JsonException $exception) {
            return ValidationResult::invalid(['Bible import JSON is invalid: '.$exception->getMessage()]);
        }

        $errors = [];

        if (! array_key_exists('book', $payload)) {
            $errors[] = 'The book field is required.';
        }

        if (! array_key_exists('chapter', $payload)) {
            $errors[] = 'The chapter field is required.';
        }

        if (! array_key_exists('verses', $payload)) {
            $errors[] = 'The verses field is required.';
        }

        if (($payload['verses'] ?? []) === [] || ! is_array($payload['verses'] ?? null)) {
            $errors[] = 'Bible payload must contain at least one verse.';
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid($errors);
    }

    public function normalize(RawKnowledgeDocument $rawDocument): array
    {
        $payload = $rawDocument->jsonPayload();
        $book = (string) $payload['book'];
        $chapter = (int) $payload['chapter'];
        $isDouayRheims = str_contains(strtolower($rawDocument->path), 'douay');
        $sourceName = $isDouayRheims ? 'Douay-Rheims Bible' : 'Bible';
        $documents = [];

        foreach ($payload['verses'] as $verse) {
            $verseNumber = (int) $verse['verse'];
            $content = trim((string) $verse['text']);
            $reference = "{$book} {$chapter}:{$verseNumber}";

            $documents[] = new NormalizedKnowledgeDocument(
                sourceType: SourceType::BibleVerse->value,
                sourceName: $sourceName,
                tradition: Tradition::Catholic->value,
                reference: $reference,
                title: $reference,
                content: $content,
                language: (string) ($payload['language'] ?? $this->language($rawDocument)),
                checksum: hash('sha256', $content),
                metadata: $this->provenance($rawDocument, [
                    'book' => $book,
                    'book_abbreviation' => $payload['book_abbreviation'] ?? null,
                    'chapter' => $chapter,
                    'verse' => $verseNumber,
                    'testament' => $payload['testament'] ?? null,
                    'source_url' => $payload['source_url'] ?? null,
                    'canon' => $isDouayRheims ? 'catholic' : null,
                    'translation' => $isDouayRheims ? 'douay_rheims' : null,
                ]),
            );
        }

        return $documents;
    }
}
