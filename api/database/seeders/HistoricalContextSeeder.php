<?php

namespace Database\Seeders;

use App\Infrastructure\History\Persistence\Mongo\Models\HistoricalContextModel;
use Illuminate\Database\Seeder;

class HistoricalContextSeeder extends Seeder
{
    public function run(): void
    {
        HistoricalContextModel::truncate();

        HistoricalContextModel::insert([
            [
                'book' => 'john',
                'chapter' => 3,
                'verse' => 16,
                'language' => 'en',
                'version' => 'drb',
                'summary' => 'John 3 records Jesus speaking with Nicodemus, a Pharisee and Jewish leader, about new birth and God sending the Son.',
                'details' => 'The verse sits within a nighttime dialogue in Jerusalem. Its language of belief, eternal life, and the sending of the Son belongs to the Gospel of John\'s wider theological emphasis on Jesus as the revealer of the Father.',
                'references' => ['John 3:1-21', 'John 20:31'],
            ],
            [
                'book' => 'genesis',
                'chapter' => 1,
                'verse' => 1,
                'language' => 'en',
                'version' => 'drb',
                'summary' => 'Genesis 1:1 opens the creation account by identifying God as the creator of heaven and earth.',
                'details' => 'The verse introduces the biblical narrative with a theological claim about creation\'s origin and dependence on God.',
                'references' => ['Genesis 1:1-2:4'],
            ],
        ]);
    }
}
