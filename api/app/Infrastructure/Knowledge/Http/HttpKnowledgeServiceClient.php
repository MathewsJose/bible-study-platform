<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Http;

use App\Application\Knowledge\Contracts\KnowledgeServiceClientInterface;
use App\Application\Knowledge\DTOs\KnowledgeServiceResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final readonly class HttpKnowledgeServiceClient implements KnowledgeServiceClientInterface
{
    public function search(string $query, array $filters = [], int $limit = 10, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('get', '/api/v1/knowledge/search', [
            'query' => $query,
            'limit' => $limit,
            ...$filters,
        ], $requestId);
    }

    public function resolveReference(string $reference, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('get', '/api/v1/knowledge/reference/'.rawurlencode($reference), [], $requestId, retry: false);
    }

    public function related(string $document, array $relationshipTypes = [], int $depth = 1, int $limit = 50, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('get', '/api/v1/knowledge/related/'.rawurlencode($document), [
            'relationship_types' => $relationshipTypes,
            'depth' => $depth,
            'limit' => $limit,
        ], $requestId);
    }

    public function retrieve(array $payload, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('post', '/api/v1/knowledge/retrieve', $payload, $requestId);
    }

    public function answer(array $payload, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('post', '/api/v1/knowledge/answer', $payload, $requestId, retry: false);
    }

    public function runAgent(array $payload, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('post', '/api/v1/knowledge/agents/run', $payload, $requestId, retry: false);
    }

    public function agentExecution(string $executionId, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('get', '/api/v1/knowledge/agents/executions/'.rawurlencode($executionId), [], $requestId, retry: false);
    }

    public function replayAgentExecution(string $executionId, array $payload = [], ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('post', '/api/v1/knowledge/agents/executions/'.rawurlencode($executionId).'/replay', $payload, $requestId, retry: false);
    }

    public function agentReplay(string $replayId, ?string $requestId = null): KnowledgeServiceResult
    {
        return $this->send('get', '/api/v1/knowledge/agent-replays/'.rawurlencode($replayId), [], $requestId, retry: false);
    }

    public function health(?string $requestId = null): bool
    {
        try {
            $response = $this->request($requestId)->get('/up');

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $uri, array $payload, ?string $requestId, bool $retry = true): KnowledgeServiceResult
    {
        $id = $requestId ?: (string) Str::uuid();
        $request = $this->request($id);

        if ($retry) {
            $request = $request->retry(
                times: (int) config('knowledge_service.retry_attempts', 2),
                sleepMilliseconds: (int) config('knowledge_service.retry_sleep_ms', 150),
                when: static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()),
            );
        }

        try {
            $response = $method === 'get'
                ? $request->get($uri, $payload)
                : $request->post($uri, $payload);
        } catch (ConnectionException $exception) {
            throw new KnowledgeServiceException('Knowledge service unavailable.', 503, [
                'service' => ['Unable to connect to the knowledge service.'],
            ]);
        } catch (RequestException $exception) {
            $body = $exception->response->json();

            throw new KnowledgeServiceException(
                message: is_array($body) && is_string($body['message'] ?? null)
                    ? $body['message']
                    : 'Knowledge service request failed.',
                statusCode: $exception->response->status(),
                errors: is_array($body) ? (array) ($body['errors'] ?? []) : [],
            );
        }

        if ($response->failed()) {
            $body = $response->json();

            throw new KnowledgeServiceException(
                message: is_array($body) && is_string($body['message'] ?? null)
                    ? $body['message']
                    : 'Knowledge service request failed.',
                statusCode: $response->status(),
                errors: is_array($body) ? (array) ($body['errors'] ?? []) : [],
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new KnowledgeServiceException('Knowledge service returned an invalid response.', 502);
        }

        return new KnowledgeServiceResult(
            data: (array) ($body['data'] ?? []),
            meta: (array) ($body['meta'] ?? []),
            requestId: $id,
        );
    }

    private function request(string $requestId): PendingRequest
    {
        $request = Http::baseUrl((string) config('knowledge_service.base_url'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('knowledge_service.connect_timeout', 2))
            ->timeout((int) config('knowledge_service.timeout', 10))
            ->withHeaders(['X-Request-ID' => $requestId]);

        $token = (string) config('knowledge_service.token', '');

        return $token === '' ? $request : $request->withToken($token);
    }
}
