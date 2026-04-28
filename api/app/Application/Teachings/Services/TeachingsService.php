<?php

namespace App\Application\Teachings\Services;

use App\Application\Teachings\DTOs\ChurchTeachingDTO;
use App\Domain\Teachings\Repositories\ChurchTeachingRepositoryInterface;

class TeachingsService
{
    public function __construct(private readonly ChurchTeachingRepositoryInterface $repository) {}

    public function getTeachings(
        string $language,
        string $version,
        string $book,
        int $chapter,
        ?int $verse = null
    ): ChurchTeachingDTO {
        $normalizedBook = strtolower(trim($book));
        $teaching = $this->repository->findByReference($language, $version, $normalizedBook, $chapter, $verse);
        $chapterTeachings = $verse === null
            ? $this->repository->findChapter($language, $version, $normalizedBook, $chapter)
            : [];
        $items = $verse === null
            ? $this->buildChapterItems($chapterTeachings)
            : $this->buildVerseItems($teaching);

        return new ChurchTeachingDTO(
            book: $normalizedBook,
            chapter: $chapter,
            verse: $verse,
            teachings: [
                'summary' => $teaching?->summary,
                'details' => $teaching?->details,
                'tradition' => $teaching?->tradition ?? 'Catholic',
                'references' => $teaching?->references ?? [],
            ],
            items: $items
        );
    }

    /**
     * @param array<int, \App\Domain\Teachings\Entities\ChurchTeaching> $teachings
     * @return array<int, string>
     */
    private function buildChapterItems(array $teachings): array
    {
        $items = [];

        foreach ($teachings as $teaching) {
            if ($teaching->summary !== null) {
                $items[] = sprintf('Verse %d: %s', $teaching->verse, $teaching->summary);
            }

            if ($teaching->details !== null) {
                $items[] = $teaching->details;
            }

            foreach ($teaching->references as $reference) {
                $items[] = sprintf('Reference: %s', $reference);
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @return array<int, string>
     */
    private function buildVerseItems(?\App\Domain\Teachings\Entities\ChurchTeaching $teaching): array
    {
        if ($teaching === null) {
            return [];
        }

        return array_values(array_filter([
            $teaching->summary,
            $teaching->details,
            ...array_map(
                fn (mixed $reference): string => sprintf('Reference: %s', (string) $reference),
                $teaching->references
            ),
        ]));
    }
}
