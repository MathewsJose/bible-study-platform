<?php

namespace App\Infrastructure\History\Persistence\Mongo\Repositories;

use App\Domain\History\Entities\HistoricalContext;
use App\Domain\History\Repositories\HistoricalContextRepositoryInterface;
use App\Infrastructure\History\Persistence\Mongo\Models\HistoricalContextModel;

class MongoHistoricalContextRepository implements HistoricalContextRepositoryInterface
{
    public function findChapter(
        string $language,
        string $version,
        string $book,
        int $chapter
    ): array {
        return HistoricalContextModel::where('book', $this->normalizeBook($book))
            ->where('chapter', $chapter)
            ->where('language', $language)
            ->where('version', $version)
            ->orderBy('verse')
            ->get()
            ->map(fn (HistoricalContextModel $model): HistoricalContext => $this->mapModel($model))
            ->all();
    }

    public function findByReference(
        string $language,
        string $version,
        string $book,
        int $chapter,
        ?int $verse
    ): ?HistoricalContext {
        $query = HistoricalContextModel::where('book', $this->normalizeBook($book))
            ->where('chapter', $chapter)
            ->where('language', $language)
            ->where('version', $version);

        if ($verse !== null) {
            $query->where('verse', $verse);
        }

        $model = $query
            ->orderBy('verse')
            ->first();

        return $model ? $this->mapModel($model) : null;
    }

    private function normalizeBook(string $book): string
    {
        return strtolower(trim($book));
    }

    private function mapModel(HistoricalContextModel $model): HistoricalContext
    {
        return new HistoricalContext(
            book: strtolower((string) $model->book),
            chapter: (int) $model->chapter,
            verse: (int) $model->verse,
            summary: $model->summary,
            details: $model->details,
            references: $model->references ?? [],
            language: (string) $model->language,
            version: (string) $model->version
        );
    }
}
