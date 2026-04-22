<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Infrastructure\Bible\Persistence\Mongo\Models\VerseModel;

class VerseSeeder extends Seeder
{
    public function run(): void
    {
        VerseModel::truncate();

        VerseModel::insert([
            [
                'book' => 'john',
                'chapter' => 3,
                'verse' => 1,
                'language' => 'en',
                'version' => 'drb',
                'text' => 'And there was a man of the Pharisees, named Nicodemus, a ruler of the Jews.',
            ],
            [
                'book' => 'john',
                'chapter' => 3,
                'verse' => 2,
                'language' => 'en',
                'version' => 'drb',
                'text' => 'This man came to Jesus by night, and said to him: Rabbi, we know that thou art come a teacher from God.',
            ],
            [
                'book' => 'john',
                'chapter' => 3,
                'verse' => 16,
                'language' => 'en',
                'version' => 'drb',
                'text' => 'For God so loved the world, as to give his only begotten Son; that whosoever believeth in him, may not perish, but may have life everlasting.',
            ],
            [
                'book' => 'john',
                'chapter' => 3,
                'verse' => 17,
                'language' => 'en',
                'version' => 'drb',
                'text' => 'For God sent not his Son into the world, to judge the world, but that the world may be saved by him.',
            ],
            [
                'book' => 'genesis',
                'chapter' => 1,
                'verse' => 1,
                'language' => 'en',
                'version' => 'drb',
                'text' => 'In the beginning God created heaven, and earth.',
            ],
        ]);
    }
}
