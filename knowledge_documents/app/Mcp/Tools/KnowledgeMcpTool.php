<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Application\Knowledge\Mcp\Services\McpToolCatalog;
use App\Application\Knowledge\Mcp\Services\McpToolInvocationService;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
abstract class KnowledgeMcpTool extends Tool
{
    abstract protected function internalName(): string;

    public function __construct(
        private readonly McpToolCatalog $catalog,
        private readonly McpToolInvocationService $invocations,
        private readonly HttpRequest $httpRequest,
    ) {}

    public function name(): string
    {
        return $this->definition()['name'];
    }

    public function title(): string
    {
        return $this->definition()['title'];
    }

    public function description(): string
    {
        return $this->definition()['description'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $definition = $this->definition();

        return [
            'name' => $definition['name'],
            'title' => $definition['title'],
            'description' => $definition['description'],
            'inputSchema' => $definition['input_schema'],
            'outputSchema' => $definition['output_schema'],
            'annotations' => [
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'openWorldHint' => false,
            ],
            '_meta' => [
                'permissions' => $definition['permissions'],
                'read_only' => $definition['read_only'],
            ],
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $result = $this->invocations->invoke($this->internalName(), $request->all(), [
            'request_id' => $this->httpRequest->headers->get('X-Request-ID'),
            'mcp_request_id' => $request->meta()['requestId'] ?? null,
        ]);

        $payload = [
            'tool' => $result->tool,
            'successful' => $result->successful,
            'status' => $result->status,
            'data' => $result->data,
            'warnings' => $result->warnings,
            'metadata' => $result->metadata,
            'latency_ms' => $result->latencyMs,
            'error' => $result->error,
        ];

        if (! $result->successful) {
            return Response::make(Response::error($result->error ?? 'MCP tool invocation failed.'))
                ->withStructuredContent($payload);
        }

        return Response::structured($payload);
    }

    /** @return array<string, mixed> */
    private function definition(): array
    {
        return $this->catalog->definition($this->internalName())->toArray();
    }
}
