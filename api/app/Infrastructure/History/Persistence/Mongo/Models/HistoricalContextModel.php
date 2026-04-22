<?php

namespace App\Infrastructure\History\Persistence\Mongo\Models;

use MongoDB\Laravel\Eloquent\Model;

class HistoricalContextModel extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'historical_contexts';
    protected $fillable = ['book', 'chapter', 'verse', 'summary', 'details', 'references', 'language', 'version'];
}
