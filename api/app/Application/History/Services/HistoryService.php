<?php

namespace App\Application\History\Services;

use App\Application\History\DTOs\HistoricalContextDTO;
use App\Domain\History\Entities\HistoricalContext;
use App\Domain\History\Repositories\HistoricalContextRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class HistoryService
{
    public function __construct(private readonly HistoricalContextRepositoryInterface $repository) {}

    public function getHistoricalContext(
        string $language,
        string $version,
        string $book,
        int $chapter,
        ?int $verse = null
    ): HistoricalContextDTO {
        $normalizedBook = strtolower(trim($book));
        $payload = Cache::remember(
            $this->cacheKey($language, $version, $normalizedBook, $chapter, $verse),
            now()->addHours(12),
            fn (): array => $this->buildPayload($language, $version, $normalizedBook, $chapter, $verse)
        );

        return new HistoricalContextDTO(
            book: $normalizedBook,
            chapter: $chapter,
            verse: $verse,
            history: $payload['history'],
            items: $payload['items']
        );
    }

    /**
     * @return array{history: array{summary: mixed, details: mixed, references: array}, items: array<int, string>}
     */
    private function buildPayload(string $language, string $version, string $book, int $chapter, ?int $verse): array
    {
        if ($verse === null) {
            $chapterContexts = $this->repository->findChapter($language, $version, $book, $chapter);
            $context = $chapterContexts[0] ?? null;

            return [
                'history' => $this->buildHistoryPayload($context),
                'items' => $this->buildChapterItems($chapterContexts),
            ];
        }

        $context = $this->repository->findByReference($language, $version, $book, $chapter, $verse);

        return [
            'history' => $this->buildHistoryPayload($context),
            'items' => $this->buildVerseItems($context),
        ];
    }

    /**
     * @param array<int, HistoricalContext> $contexts
     * @return array<int, string>
     */
    private function buildChapterItems(array $contexts): array
    {
        $items = [];

        foreach ($contexts as $context) {
            if ($context->summary !== null) {
                $items[] = sprintf('Verse %d: %s', $context->verse, $context->summary);
            }

            if ($context->details !== null) {
                $items[] = $context->details;
            }

            foreach ($context->references as $reference) {
                $items[] = sprintf('Reference: %s', $reference);
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @return array<int, string>
     */
    private function buildVerseItems(?HistoricalContext $context): array
    {
        if ($context === null) {
            return [];
        }

        return array_values(array_filter([
            $context->summary,
            $context->details,
            ...array_map(
                fn (mixed $reference): string => sprintf('Reference: %s', (string) $reference),
                $context->references
            ),
        ]));
    }

    /**
     * @return array{summary: mixed, details: mixed, references: array}
     */
    private function buildHistoryPayload(?HistoricalContext $context): array
    {
        return [
            'summary' => $context?->summary,
            'details' => $context?->details,
            'references' => $context?->references ?? [],
        ];
    }

    private function cacheKey(string $language, string $version, string $book, int $chapter, ?int $verse): string
    {
        $reference = $verse === null ? 'chapter' : "verse:$verse";

        return "history:$language:$version:$book:$chapter:$reference";
    }
}
