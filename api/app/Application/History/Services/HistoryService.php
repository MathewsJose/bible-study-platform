<?php

namespace App\Application\History\Services;

use App\Application\History\DTOs\HistoricalContextDTO;
use App\Domain\History\Repositories\HistoricalContextRepositoryInterface;

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
        $context = $this->repository->findByReference($language, $version, $normalizedBook, $chapter, $verse);
        $chapterContexts = $verse === null
            ? $this->repository->findChapter($language, $version, $normalizedBook, $chapter)
            : [];
        $items = $verse === null
            ? $this->buildChapterItems($chapterContexts)
            : $this->buildVerseItems($context);

        return new HistoricalContextDTO(
            book: $normalizedBook,
            chapter: $chapter,
            verse: $verse,
            history: [
                'summary' => $context?->summary,
                'details' => $context?->details,
                'references' => $context?->references ?? [],
            ],
            items: $items
        );
    }

    /**
     * @param array<int, \App\Domain\History\Entities\HistoricalContext> $contexts
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
    private function buildVerseItems(?\App\Domain\History\Entities\HistoricalContext $context): array
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
}
