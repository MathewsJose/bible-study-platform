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
        int $verse
    ): ChurchTeachingDTO {
        $normalizedBook = strtolower(trim($book));
        $teaching = $this->repository->findByReference($language, $version, $normalizedBook, $chapter, $verse);

        return new ChurchTeachingDTO(
            book: $normalizedBook,
            chapter: $chapter,
            verse: $verse,
            teachings: [
                'summary' => $teaching?->summary,
                'details' => $teaching?->details,
                'tradition' => $teaching?->tradition ?? 'Catholic',
                'references' => $teaching?->references ?? [],
            ]
        );
    }
}
