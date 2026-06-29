<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use Throwable;

final readonly class BibleImporter
{
    public const SOURCE_NAME = 'Bible';

    /**
     * @throws JsonException
     * @throws ValidationException
     */
    public function importFile(string $path): ImportResult
    {
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

        return $this->import($payload, $path);
    }

    /**
     * @param  array<mixed>  $payload
     *
     * @throws ValidationException
     */
    public function import(array $payload, ?string $path = null): ImportResult
    {
        $validated = $this->validatePayload($payload);
        $book = (string) $validated['book'];
        $chapter = (int) $validated['chapter'];

        $imported = 0;
        $skippedDuplicates = 0;
        $failures = 0;

        /** @var list<array{verse: int, text: string}> $verses */
        $verses = $validated['verses'];

        foreach ($verses as $verse) {
            $document = $this->documentPayload($book, $chapter, $verse);

            try {
                $created = DB::transaction(function () use ($document): bool {
                    if ($this->documentExists($document)) {
                        return false;
                    }

                    KnowledgeDocumentRecord::query()->create($document);

                    return true;
                });

                if ($created) {
                    $imported++;
                } else {
                    $skippedDuplicates++;
                }
            } catch (QueryException $exception) {
                if ($this->isDuplicateKeyException($exception)) {
                    $skippedDuplicates++;

                    continue;
                }

                $failures++;
                $this->logFailure($document, $exception);
            } catch (Throwable $exception) {
                $failures++;
                $this->logFailure($document, $exception);
            }
        }

        $result = new ImportResult($imported, $skippedDuplicates, $failures);

        Log::info('Bible import completed.', [
            'path' => $path,
            'book' => $book,
            'chapter' => $chapter,
            'imported' => $result->imported,
            'skipped_duplicates' => $result->skippedDuplicates,
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
        ])->validate();

        return $validated;
    }

    /**
     * @param  array{verse: int, text: string}  $verse
     * @return array<string, mixed>
     */
    private function documentPayload(string $book, int $chapter, array $verse): array
    {
        $reference = "{$book} {$chapter}:{$verse['verse']}";

        return [
            'source_type' => SourceType::BibleVerse->value,
            'source_name' => self::SOURCE_NAME,
            'reference' => $reference,
            'title' => $reference,
            'content' => trim($verse['text']),
            'tradition' => Tradition::Catholic->value,
            'metadata' => [
                'book' => $book,
                'chapter' => $chapter,
                'verse' => $verse['verse'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function documentExists(array $document): bool
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', $document['source_type'])
            ->where('source_name', $document['source_name'])
            ->where('reference', $document['reference'])
            ->exists();
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true);
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
