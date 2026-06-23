<?php

use Illuminate\Support\Facades\Route;
use App\Interfaces\Http\Controllers\BibleController;
use App\Interfaces\Http\Controllers\HistoryController;
use App\Interfaces\Http\Controllers\StudyController;
use App\Interfaces\Http\Controllers\TeachingsController;

Route::get('bible', [BibleController::class, 'chapter']);
Route::get('history', [HistoryController::class, 'show']);
Route::get('study', [StudyController::class, 'show']);
Route::get('teachings', [TeachingsController::class, 'show']);

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->get('user', [\App\Http\Controllers\UserController::class, 'show']);
