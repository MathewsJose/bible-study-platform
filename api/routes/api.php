<?php

use Illuminate\Support\Facades\Route;
use App\Interfaces\Http\Controllers\BibleController;
use App\Interfaces\Http\Controllers\HistoryController;
use App\Interfaces\Http\Controllers\TeachingsController;

Route::get('bible', [BibleController::class, 'chapter']);
Route::get('history', [HistoryController::class, 'show']);
Route::get('teachings', [TeachingsController::class, 'show']);
