<?php

namespace App\Infrastructure\Teachings\Persistence\Mongo\Models;

use MongoDB\Laravel\Eloquent\Model;

class ChurchTeachingModel extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'church_teachings';
    protected $fillable = ['book', 'chapter', 'verse', 'summary', 'details', 'tradition', 'references', 'language', 'version'];
}
