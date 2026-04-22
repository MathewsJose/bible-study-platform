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
        int $verse
    ): HistoricalContextDTO {
        $normalizedBook = strtolower(trim($book));
        $context = $this->repository->findByReference($language, $version, $normalizedBook, $chapter, $verse);

        return new HistoricalContextDTO(
            book: $normalizedBook,
            chapter: $chapter,
            verse: $verse,
            history: [
                'summary' => $context?->summary,
                'details' => $context?->details,
                'references' => $context?->references ?? [],
            ]
        );
    }
}
