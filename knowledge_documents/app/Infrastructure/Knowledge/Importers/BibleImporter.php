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

final readonly class BibleImporter
{
    use TracksImportManifests;

    public const SOURCE_NAME = 'Bible';

    public function __construct(private KnowledgeDocumentService $documents) {}

    /**
     * @throws JsonException
     * @throws ValidationException
     */
    public function importFile(string $path, array $metadata = []): ImportResult
    {
        $manifest = $this->startManifest($path, 'bible', self::SOURCE_NAME, $metadata);

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

            $manifest->update([
                'source_url' => $payload['source_url'] ?? null,
                'license' => $payload['license'] ?? null,
                'license_url' => $payload['license_url'] ?? null,
                'rights_notes' => $payload['rights_notes'] ?? null,
                'language' => $payload['language'] ?? 'en',
            ]);

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
            $document = $this->documentPayload($book, $chapter, $verse, $sourceMetadata);

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

        $result = new ImportResult(
            created: $imported,
            updated: $updated,
            skipped: $skipped,
            failures: $failures
        );

        Log::info('Bible import completed.', [
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
     * @return array{book: string, chapter: int, verses: list<array{verse: int, text: string}>}
     *
     * @throws ValidationException
     */
    private function validatePayload(array $payload): array
    {
        /** @var array{book: string, chapter: int, verses: list<array{verse: int, text: string}>} $validated */
        $validated = Validator::make($payload, [
            'book' => ['required', 'string', 'max:120'],
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
     * @param  array{verse: int, text: string}  $verse
     * @return array<string, mixed>
     */
    private function documentPayload(string $book, int $chapter, array $verse, ?SourceMetadata $sourceMetadata = null): array
    {
        $reference = "{$book} {$chapter}:{$verse['verse']}";

        $metadata = [
            'book' => $book,
            'chapter' => $chapter,
            'verse' => $verse['verse'],
        ];

        if ($sourceMetadata) {
            $metadata = array_merge($metadata, $sourceMetadata->toArray());
        }

        return [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => self::SOURCE_NAME,
            'reference' => $reference,
            'title' => $reference,
            'content' => trim($verse['text']),
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
            'reference' => $document['reference'] ?? null,
            'exception' => $exception,
        ]);
    }
}
