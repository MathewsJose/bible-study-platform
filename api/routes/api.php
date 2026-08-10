<?php

use App\Http\Controllers\UserController;
use App\Interfaces\Http\Controllers\BibleController;
use App\Interfaces\Http\Controllers\HistoryController;
use App\Interfaces\Http\Controllers\Knowledge\KnowledgeController;
use App\Interfaces\Http\Controllers\StudyController;
use App\Interfaces\Http\Controllers\TeachingsController;
use Illuminate\Support\Facades\Route;

Route::get('bible', [BibleController::class, 'chapter']);
Route::get('history', [HistoryController::class, 'show']);
Route::get('study', [StudyController::class, 'show']);
Route::get('teachings', [TeachingsController::class, 'show']);

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->get('user', [UserController::class, 'show']);

Route::prefix('v1/knowledge')->group(function (): void {
    Route::middleware('throttle:api')->group(function (): void {
        Route::get('search', [KnowledgeController::class, 'search']);
        Route::get('reference/{reference}', [KnowledgeController::class, 'reference']);
        Route::get('related/{document}', [KnowledgeController::class, 'related']);
        Route::post('retrieve', [KnowledgeController::class, 'retrieve']);
    });

    Route::middleware(['auth:sanctum', 'throttle:knowledge-ai'])->group(function (): void {
        Route::post('answer', [KnowledgeController::class, 'answer']);
        Route::post('agents/run', [KnowledgeController::class, 'agent']);
        Route::get('agents/executions/{id}', [KnowledgeController::class, 'agentExecution']);
        Route::post('agents/executions/{id}/replay', [KnowledgeController::class, 'replayAgentExecution']);
        Route::get('agent-replays/{id}', [KnowledgeController::class, 'agentReplay']);
    });
});
