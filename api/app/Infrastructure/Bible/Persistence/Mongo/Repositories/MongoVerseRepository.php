<?php 

namespace App\Infrastructure\Bible\Persistence\Mongo\Repositories;

use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use App\Domain\Bible\Entities\Verse;
use App\Infrastructure\Bible\Persistence\Mongo\Models\VerseModel;
use MongoDB\BSON\Regex;

class MongoVerseRepository implements VerseRepositoryInterface
{
    /**
     * @return array<int, Verse>
     */
    public function findChapter(string $book, int $chapter, ?string $language = null, ?string $version = null): array
    {
        $query = VerseModel::where('book', new Regex('^'.preg_quote($book, '/').'$', 'i'))
            ->where('chapter', $chapter);

        if ($language !== null) {
            $query->where('language', $language);
        }

        if ($version !== null) {
            $query->where('version', $version);
        }

        return $query->orderBy('verse')
            ->get()
            ->map(fn (VerseModel $model): Verse => $this->toEntity($model))
            ->all();
    }

    public function findByReference(string $book, int $chapter, int $verse, ?string $language = null, ?string $version = null): ?Verse
    {
        $query = VerseModel::where('book', new Regex('^'.preg_quote($book, '/').'$', 'i'))
            ->where('chapter', $chapter)
            ->where('verse', $verse);

        if ($language !== null) {
            $query->where('language', $language);
        }

        if ($version !== null) {
            $query->where('version', $version);
        }

        $model = $query->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(Verse $verse): void
    {
        VerseModel::updateOrCreate(
            ['book' => $verse->book, 'chapter' => $verse->chapter, 'verse' => $verse->verse, 'language' => 'en', 'version' => 'drb'],
            ['text' => $verse->text]
        );
    }

    private function toEntity(VerseModel $model): Verse
    {
        return new Verse(
            (string) $model->id,
            strtolower((string) $model->book),
            (int) $model->chapter,
            (int) $model->verse,
            (string) $model->text
        );
    }
}
