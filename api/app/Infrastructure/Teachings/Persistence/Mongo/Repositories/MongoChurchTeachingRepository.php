<?php

namespace App\Infrastructure\Teachings\Persistence\Mongo\Repositories;

use App\Domain\Teachings\Entities\ChurchTeaching;
use App\Domain\Teachings\Repositories\ChurchTeachingRepositoryInterface;
use App\Infrastructure\Teachings\Persistence\Mongo\Models\ChurchTeachingModel;
use MongoDB\BSON\Regex;

class MongoChurchTeachingRepository implements ChurchTeachingRepositoryInterface
{
    public function findChapter(
        string $language,
        string $version,
        string $book,
        int $chapter
    ): array {
        return ChurchTeachingModel::where('book', new Regex('^'.preg_quote($book, '/').'$', 'i'))
            ->where('chapter', $chapter)
            ->where('language', $language)
            ->where('version', $version)
            ->orderBy('verse')
            ->get()
            ->map(fn (ChurchTeachingModel $model): ChurchTeaching => $this->mapModel($model))
            ->all();
    }

    public function findByReference(
        string $language,
        string $version,
        string $book,
        int $chapter,
        ?int $verse
    ): ?ChurchTeaching {
        $query = ChurchTeachingModel::where('book', new Regex('^'.preg_quote($book, '/').'$', 'i'))
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

    private function mapModel(ChurchTeachingModel $model): ChurchTeaching
    {
        return new ChurchTeaching(
            book: strtolower((string) $model->book),
            chapter: (int) $model->chapter,
            verse: (int) $model->verse,
            summary: $model->summary,
            details: $model->details,
            tradition: $model->tradition ?? 'Catholic',
            references: $model->references ?? [],
            language: (string) $model->language,
            version: (string) $model->version
        );
    }
}
