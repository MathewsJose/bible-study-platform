<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\AnswerController;
use App\Presentation\Http\Controllers\KnowledgeDocumentController;
use App\Presentation\Http\Controllers\RetrievalController;
use App\Presentation\Http\Controllers\RetrievalEvaluationController;
use Illuminate\Support\Facades\Route;

Route::prefix('documents')->group(function (): void {
    Route::get('/', [KnowledgeDocumentController::class, 'index']);
    Route::post('/', [KnowledgeDocumentController::class, 'store']);
    Route::post('/search', [KnowledgeDocumentController::class, 'fullTextSearch']);
    Route::post('/semantic-search', [KnowledgeDocumentController::class, 'semanticSearch']);
    Route::post('/hybrid-search', [KnowledgeDocumentController::class, 'hybridSearch']);
    Route::get('/{id}', [KnowledgeDocumentController::class, 'show']);
    Route::put('/{id}', [KnowledgeDocumentController::class, 'update']);
    Route::delete('/{id}', [KnowledgeDocumentController::class, 'destroy']);
});

Route::post('/evaluations/retrieval', [RetrievalEvaluationController::class, 'store']);
Route::post('/retrieval', [RetrievalController::class, 'store']);
Route::post('/answers', [AnswerController::class, 'store']);
