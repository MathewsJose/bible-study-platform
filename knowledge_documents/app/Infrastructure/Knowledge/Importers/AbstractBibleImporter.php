<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Domain\Knowledge\Enums\ImportStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Domain\Knowledge\ValueObjects\SourceMetadata;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

abstract readonly class AbstractBibleImporter
{
    use TracksImportManifests;

    public function __construct(protected KnowledgeDocumentService $documents) {}

    abstract public function sourceName(): string;
    protected function shouldImportChapters(): bool
    {
        return false;
    }

    /**
     * @throws JsonException
     * @throws ValidationException
     */
    public function importFile(string $path, array $metadata = []): ImportResult
    {
        $manifest = $this->startManifest($path, 'bible', $this->sourceName(), $metadata);

        try {
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw ValidationException::withMessages([
                    'path' => "Unable to read Bible import file [{$path}].",
                ]);
            }

            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                throw ValidationException::withMessages([
                    'file' => 'Bible import file must contain a JSON object.',
                ]);
            }

            $manifest->update(array_filter([
                'source_url' => $payload['source_url'] ?? null,
                'license' => $payload['license'] ?? null,
                'license_url' => $payload['license_url'] ?? null,
                'rights_notes' => $payload['rights_notes'] ?? null,
                'language' => $payload['language'] ?? 'en',
            ]));

            $result = $this->import($payload, $path, $manifest->toSourceMetadata());

            $this->finishManifest($manifest, $result);

            return $result;
        } catch (Throwable $exception) {
            $this->failManifest($manifest, $exception);

            throw $exception;
        }
    }

    /**
     * @param  array<mixed>  $payload
     *
     * @throws ValidationException
     */
    public function import(array $payload, ?string $path = null, ?SourceMetadata $sourceMetadata = null): ImportResult
    {
        $validated = $this->validatePayload($payload);
        $book = (string) $validated['book'];
        $chapter = (int) $validated['chapter'];

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failures = 0;

        /** @var list<array{verse: int, text: string}> $verses */
        $verses = $validated['verses'];

        foreach ($verses as $verse) {
            $document = $this->documentPayload($validated, $verse, $sourceMetadata);

            try {
                $status = $this->documents->import($document);

                match ($status) {
                    ImportStatus::Created => $imported++,
                    ImportStatus::Updated => $updated++,
                    ImportStatus::Skipped => $skipped++,
                };
            } catch (Throwable $exception) {
                $failures++;
                $this->logFailure($document, $exception);
            }
        }

        if ($this->shouldImportChapters()) {
            $chapterDocument = $this->chapterDocumentPayload($validated, $sourceMetadata);

            try {
                $status = $this->documents->import($chapterDocument);

                match ($status) {
                    ImportStatus::Created => $imported++,
                    ImportStatus::Updated => $updated++,
                    ImportStatus::Skipped => $skipped++,
                };
            } catch (Throwable $exception) {
                $failures++;
                $this->logFailure($chapterDocument, $exception);
            }
        }

        $result = new ImportResult(
            created: $imported,
            updated: $updated,
            skipped: $skipped,
            failures: $failures
        );

        Log::info($this->sourceName() . ' import completed.', [
            'path' => $path,
            'book' => $book,
            'chapter' => $chapter,
            'imported' => $result->created,
            'updated' => $result->updated,
            'skipped' => $result->skipped,
            'failures' => $result->failures,
        ]);

        return $result;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array{book: string, chapter: int, verses: list<array{verse: int, text: string}>, book_abbreviation?: string, testament?: string}
     *
     * @throws ValidationException
     */
    protected function validatePayload(array $payload): array
    {
        /** @var array{book: string, chapter: int, verses: list<array{verse: int, text: string}>, book_abbreviation?: string, testament?: string} $validated */
        $validated = Validator::make($payload, [
            'book' => ['required', 'string', 'max:120'],
            'book_abbreviation' => ['nullable', 'string', 'max:20'],
            'testament' => ['nullable', 'string', 'max:50'],
            'chapter' => ['required', 'integer', 'min:1'],
            'verses' => ['required', 'array', 'min:1'],
            'verses.*.verse' => ['required', 'integer', 'min:1'],
            'verses.*.text' => ['required', 'string'],
            'source_url' => ['nullable', 'string', 'url'],
            'license' => ['nullable', 'string'],
            'license_url' => ['nullable', 'string', 'url'],
            'rights_notes' => ['nullable', 'string'],
            'language' => ['nullable', 'string', 'max:20'],
        ])->validate();

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validatedPayload
     * @param  array{verse: int, text: string}  $verse
     * @return array<string, mixed>
     */
    protected function documentPayload(array $validatedPayload, array $verse, ?SourceMetadata $sourceMetadata = null): array
    {
        $book = $validatedPayload['book'];
        $chapter = $validatedPayload['chapter'];
        $reference = "{$book} {$chapter}:{$verse['verse']}";

        $metadata = array_filter([
            'book' => $book,
            'book_abbreviation' => $validatedPayload['book_abbreviation'] ?? null,
            'chapter' => $chapter,
            'verse' => $verse['verse'],
            'testament' => $validatedPayload['testament'] ?? null,
        ]);

        if ($sourceMetadata) {
            $metadata = array_merge($metadata, $sourceMetadata->toArray());
        }

        return [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => $this->sourceName(),
            'reference' => $reference,
            'title' => $reference,
            'content' => trim($verse['text']),
            'tradition' => Tradition::Catholic->value,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $validatedPayload
     * @return array<string, mixed>
     */
    protected function chapterDocumentPayload(array $validatedPayload, ?SourceMetadata $sourceMetadata = null): array
    {
        $book = $validatedPayload['book'];
        $chapter = $validatedPayload['chapter'];
        $reference = "{$book} {$chapter}";

        $verses = $validatedPayload['verses'];
        $content = '';
        foreach ($verses as $verse) {
            $content .= "[{$verse['verse']}] {$verse['text']} ";
        }
        $content = trim($content);

        $metadata = array_filter([
            'book' => $book,
            'book_abbreviation' => $validatedPayload['book_abbreviation'] ?? null,
            'chapter' => $chapter,
            'testament' => $validatedPayload['testament'] ?? null,
            'verse_count' => count($verses),
        ]);

        if ($sourceMetadata) {
            $metadata = array_merge($metadata, $sourceMetadata->toArray());
        }

        return [
            'source_type' => SourceType::BibleChapter->value,
            'source_name' => $this->sourceName(),
            'reference' => $reference,
            'title' => "{$book} Chapter {$chapter}",
            'content' => $content,
            'tradition' => Tradition::Catholic->value,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function logFailure(array $document, Throwable $exception): void
    {
        Log::warning('Bible verse import failed.', [
            'source_name' => $this->sourceName(),
            'reference' => $document['reference'] ?? null,
            'exception' => $exception,
        ]);
    }
}
