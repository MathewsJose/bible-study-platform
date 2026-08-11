<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\AnswerController;
use App\Presentation\Http\Controllers\AgentController;
use App\Presentation\Http\Controllers\KnowledgeDocumentController;
use App\Presentation\Http\Controllers\KnowledgeIntegrationController;
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
Route::post('/retrieval', [RetrievalController::class, 'store'])->middleware('throttle:knowledge-ai-retrieval');
Route::post('/answers', [AnswerController::class, 'store'])->middleware('throttle:knowledge-ai-answer');
Route::post('/agents/run', [AgentController::class, 'store'])->middleware('throttle:knowledge-ai-agent');

Route::prefix('v1/knowledge')->group(function (): void {
    Route::get('/search', [KnowledgeIntegrationController::class, 'search']);
    Route::get('/reference/{reference}', [KnowledgeIntegrationController::class, 'reference']);
    Route::get('/related/{document}', [KnowledgeIntegrationController::class, 'related']);
    Route::post('/retrieve', [KnowledgeIntegrationController::class, 'retrieve'])->middleware('throttle:knowledge-ai-retrieval');
    Route::post('/answer', [KnowledgeIntegrationController::class, 'answer'])->middleware('throttle:knowledge-ai-answer');
    Route::post('/agents/run', [KnowledgeIntegrationController::class, 'agent'])->middleware('throttle:knowledge-ai-agent');
    Route::get('/agents/executions/{id}', [KnowledgeIntegrationController::class, 'execution']);
    Route::post('/agents/executions/{id}/replay', [KnowledgeIntegrationController::class, 'replay'])->middleware('throttle:knowledge-ai-replay');
    Route::get('/agent-replays/{id}', [KnowledgeIntegrationController::class, 'replayStatus']);
});
