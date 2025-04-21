<?php 

namespace App\Infrastructure\Bible\Persistence\Mongo\Repositories;

use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use App\Domain\Bible\Entities\Verse;
use App\Infrastructure\Bible\Persistence\Mongo\Models\VerseModel;

class MongoVerseRepository implements VerseRepositoryInterface
{
    public function findByReference(string $book, int $chapter, int $verse): ?Verse
    {
        $model = VerseModel::where([
            'book' => $book,
            'chapter' => $chapter,
            'verse' => $verse
        ])->first();

        return $model ? new Verse($model->id, $model->book, $model->chapter, $model->verse, $model->text) : null;
    }

    public function save(Verse $verse): void
    {
        VerseModel::updateOrCreate(
            ['book' => $verse->book, 'chapter' => $verse->chapter, 'verse' => $verse->verse],
            ['text' => $verse->text]
        );
    }
}
