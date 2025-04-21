<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Infrastructure\Bible\Persistence\Mongo\Models\VerseModel;

class VerseSeeder extends Seeder
{
    public function run(): void
    {
        VerseModel::truncate(); // clears old data

        VerseModel::insert([
            [
                'book' => 'John',
                'chapter' => 3,
                'verse' => 16,
                'text' => 'For God so loved the world...',
            ],
            [
                'book' => 'Genesis',
                'chapter' => 1,
                'verse' => 1,
                'text' => 'In the beginning God created the heavens and the earth.',
            ],
        ]);
    }
}

