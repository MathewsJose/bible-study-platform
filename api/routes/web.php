<?php

use Illuminate\Support\Facades\Route;
use App\Interfaces\Http\Controllers\BibleController;

Route::get('/', function () {
    return view('welcome');
});

// Kept as a legacy path for existing clients while new REST endpoints live in routes/api.php.
Route::prefix('api')->group(function () {
    Route::get('verse/{book}/{chapter}/{verse}', [BibleController::class, 'show']);
});
