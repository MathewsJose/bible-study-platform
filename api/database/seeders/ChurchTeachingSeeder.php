<?php

namespace Database\Seeders;

use App\Infrastructure\Teachings\Persistence\Mongo\Models\ChurchTeachingModel;
use Illuminate\Database\Seeder;

class ChurchTeachingSeeder extends Seeder
{
    public function run(): void
    {
        ChurchTeachingModel::truncate();

        ChurchTeachingModel::insert([
            [
                'book' => 'john',
                'chapter' => 3,
                'verse' => 16,
                'language' => 'en',
                'version' => 'drb',
                'summary' => 'John 3:16 is commonly read in Catholic teaching as a concise expression of God\'s love and the saving mission of the Son.',
                'details' => 'Catholic theology connects this passage with the Incarnation, redemption, faith in Christ, and the gift of eternal life.',
                'tradition' => 'Catholic',
                'references' => ['CCC 458', 'CCC 679'],
            ],
            [
                'book' => 'genesis',
                'chapter' => 1,
                'verse' => 1,
                'language' => 'en',
                'version' => 'drb',
                'summary' => 'Genesis 1:1 supports the doctrine that God freely created all things.',
                'details' => 'Catholic teaching reads creation as ordered, good, and dependent on God, while distinguishing the Creator from creation.',
                'tradition' => 'Catholic',
                'references' => ['CCC 279', 'CCC 290'],
            ],
        ]);
    }
}
