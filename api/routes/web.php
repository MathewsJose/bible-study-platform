<?php

use Illuminate\Support\Facades\Route;
use App\Interfaces\Http\Controllers\BibleController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api')->group(function () {
    Route::get('verse/{book}/{chapter}/{verse}', [BibleController::class, 'show']);
});
