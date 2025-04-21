<?php

namespace App\Application\Bible\UseCases;

use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use App\Application\Bible\DTOs\VerseDTO;

class GetVerseUseCase
{
    public function __construct(private readonly VerseRepositoryInterface $repository) {}

    public function execute(string $book, int $chapter, int $verse): ?VerseDTO
    {
        $verseEntity = $this->repository->findByReference($book, $chapter, $verse);

        if (!$verseEntity) return null;

        return new VerseDTO(
            $verseEntity->book,
            $verseEntity->chapter,
            $verseEntity->verse,
            $verseEntity->text
        );
    }
}
