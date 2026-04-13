<?php

namespace App\Infrastructure\Teachings\Persistence\Mongo\Repositories;

use App\Domain\Teachings\Entities\ChurchTeaching;
use App\Domain\Teachings\Repositories\ChurchTeachingRepositoryInterface;
use App\Infrastructure\Teachings\Persistence\Mongo\Models\ChurchTeachingModel;
use MongoDB\BSON\Regex;

class MongoChurchTeachingRepository implements ChurchTeachingRepositoryInterface
{
    public function findByReference(
        string $language,
        string $version,
        string $book,
        int $chapter,
        int $verse
    ): ?ChurchTeaching {
        $model = ChurchTeachingModel::where('book', new Regex('^'.preg_quote($book, '/').'$', 'i'))
            ->where('chapter', $chapter)
            ->where('verse', $verse)
            ->where('language', $language)
            ->where('version', $version)
            ->first();

        return $model ? new ChurchTeaching(
            book: strtolower((string) $model->book),
            chapter: (int) $model->chapter,
            verse: (int) $model->verse,
            summary: $model->summary,
            details: $model->details,
            tradition: $model->tradition ?? 'Catholic',
            references: $model->references ?? [],
            language: (string) $model->language,
            version: (string) $model->version
        ) : null;
    }
}
