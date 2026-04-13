<?php

namespace App\Infrastructure\History\Persistence\Mongo\Repositories;

use App\Domain\History\Entities\HistoricalContext;
use App\Domain\History\Repositories\HistoricalContextRepositoryInterface;
use App\Infrastructure\History\Persistence\Mongo\Models\HistoricalContextModel;
use MongoDB\BSON\Regex;

class MongoHistoricalContextRepository implements HistoricalContextRepositoryInterface
{
    public function findByReference(
        string $language,
        string $version,
        string $book,
        int $chapter,
        int $verse
    ): ?HistoricalContext {
        $model = HistoricalContextModel::where('book', new Regex('^'.preg_quote($book, '/').'$', 'i'))
            ->where('chapter', $chapter)
            ->where('verse', $verse)
            ->where('language', $language)
            ->where('version', $version)
            ->first();

        return $model ? new HistoricalContext(
            book: strtolower((string) $model->book),
            chapter: (int) $model->chapter,
            verse: (int) $model->verse,
            summary: $model->summary,
            details: $model->details,
            references: $model->references ?? [],
            language: (string) $model->language,
            version: (string) $model->version
        ) : null;
    }
}
