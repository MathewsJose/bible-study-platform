<?php

namespace App\Application\Teachings\Services;

use App\Application\Teachings\DTOs\ChurchTeachingDTO;
use App\Domain\Teachings\Entities\ChurchTeaching;
use App\Domain\Teachings\Repositories\ChurchTeachingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

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
        $payload = Cache::remember(
            $this->cacheKey($language, $version, $normalizedBook, $chapter, $verse),
            now()->addHours(12),
            fn (): array => $this->buildPayload($language, $version, $normalizedBook, $chapter, $verse)
        );

        return new ChurchTeachingDTO(
            book: $normalizedBook,
            chapter: $chapter,
            verse: $verse,
            teachings: $payload['teachings'],
            items: $payload['items']
        );
    }

    /**
     * @return array{teachings: array{summary: mixed, details: mixed, tradition: string, references: array}, items: array<int, string>}
     */
    private function buildPayload(string $language, string $version, string $book, int $chapter, ?int $verse): array
    {
        if ($verse === null) {
            $chapterTeachings = $this->repository->findChapter($language, $version, $book, $chapter);
            $teaching = $chapterTeachings[0] ?? null;

            return [
                'teachings' => $this->buildTeachingsPayload($teaching),
                'items' => $this->buildChapterItems($chapterTeachings),
            ];
        }

        $teaching = $this->repository->findByReference($language, $version, $book, $chapter, $verse);

        return [
            'teachings' => $this->buildTeachingsPayload($teaching),
            'items' => $this->buildVerseItems($teaching),
        ];
    }

    /**
     * @param array<int, ChurchTeaching> $teachings
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
    private function buildVerseItems(?ChurchTeaching $teaching): array
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

    /**
     * @return array{summary: mixed, details: mixed, tradition: string, references: array}
     */
    private function buildTeachingsPayload(?ChurchTeaching $teaching): array
    {
        return [
            'summary' => $teaching?->summary,
            'details' => $teaching?->details,
            'tradition' => $teaching?->tradition ?? 'Catholic',
            'references' => $teaching?->references ?? [],
        ];
    }

    private function cacheKey(string $language, string $version, string $book, int $chapter, ?int $verse): string
    {
        $reference = $verse === null ? 'chapter' : "verse:$verse";

        return "teachings:$language:$version:$book:$chapter:$reference";
    }
}
