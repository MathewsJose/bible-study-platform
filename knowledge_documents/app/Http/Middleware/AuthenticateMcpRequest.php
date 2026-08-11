<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateMcpRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $maxPayloadBytes = (int) config('ai_security.limits.max_mcp_payload_bytes', 32768);
        $content = $request->getContent();
        $contentLength = (int) $request->header('Content-Length', strlen($content));

        if ($contentLength > $maxPayloadBytes || strlen($content) > $maxPayloadBytes) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => [
                    'code' => -32003,
                    'message' => 'RESOURCE_LIMIT_EXCEEDED',
                ],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (! (bool) config('mcp_knowledge.enabled', false)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => [
                    'code' => -32000,
                    'message' => 'MCP server is disabled.',
                ],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $token = (string) config('mcp_knowledge.token', '');

        if ($token === '' || $request->bearerToken() !== $token) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => $request->input('id'),
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized MCP request.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
