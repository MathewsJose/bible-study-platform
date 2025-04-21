<?php

use Illuminate\Support\Facades\Route;
use App\Interface\Http\Controllers\Bible\VerseController;
use App\Interfaces\Http\Controllers\BibleController;

Route::prefix('api')->group(function () {
    Route::get('verse/{book}/{chapter}/{verse}', [BibleController::class, 'show']);
});
