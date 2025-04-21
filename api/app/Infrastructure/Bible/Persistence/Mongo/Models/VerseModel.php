<?php

namespace App\Infrastructure\Bible\Persistence\Mongo\Models;

use MongoDB\Laravel\Eloquent\Model;

class VerseModel extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verses';
    protected $fillable = ['book', 'chapter', 'verse', 'text'];
}
