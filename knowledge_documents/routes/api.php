<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\KnowledgeDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('documents')->group(function (): void {
    Route::get('/', [KnowledgeDocumentController::class, 'index']);
    Route::post('/', [KnowledgeDocumentController::class, 'store']);
    Route::post('/search', [KnowledgeDocumentController::class, 'fullTextSearch']);
    Route::post('/semantic-search', [KnowledgeDocumentController::class, 'semanticSearch']);
    Route::get('/{id}', [KnowledgeDocumentController::class, 'show']);
    Route::put('/{id}', [KnowledgeDocumentController::class, 'update']);
    Route::delete('/{id}', [KnowledgeDocumentController::class, 'destroy']);
});
