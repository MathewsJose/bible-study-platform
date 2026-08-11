<?php

use App\Http\Middleware\AuthenticateMcpRequest;
use App\Mcp\Servers\KnowledgeMcpServer;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/', function () {
    return view('welcome');
});

Mcp::web((string) config('mcp_knowledge.route', 'mcp/knowledge'), KnowledgeMcpServer::class)
    ->middleware([
        AuthenticateMcpRequest::class,
        'throttle:mcp',
    ])
    ->withoutMiddleware([ValidateCsrfToken::class]);
