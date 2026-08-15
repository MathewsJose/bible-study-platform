<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeServiceClientInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Knowledge\Http\KnowledgeServiceException;
use App\Interfaces\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class KnowledgeController extends Controller
{
    public function __construct(private readonly KnowledgeServiceClientInterface $knowledge) {}

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'source_type' => ['sometimes', 'string'],
            'book' => ['sometimes', 'string'],
            'chapter' => ['sometimes', 'integer', 'min:1'],
            'translation' => ['sometimes', 'string'],
            'language' => ['sometimes', 'string'],
            'tradition' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid knowledge search request.', 400, $validator->errors());
        }

        return $this->respond(fn () => $this->knowledge->search(
            query: (string) $request->query('query'),
            filters: $request->only(['source_type', 'book', 'chapter', 'translation', 'language', 'tradition']),
            limit: (int) $request->query('limit', 10),
            requestId: $this->requestId($request),
        ));
    }

    public function reference(Request $request, string $reference): JsonResponse
    {
        return $this->respond(fn () => $this->knowledge->resolveReference(urldecode($reference), $this->requestId($request)));
    }

    public function related(Request $request, string $document): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'relationship_types' => ['sometimes', 'array'],
            'relationship_types.*' => ['string'],
            'depth' => ['sometimes', 'integer', 'min:1', 'max:2'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid related knowledge request.', 400, $validator->errors());
        }

        return $this->respond(fn () => $this->knowledge->related(
            document: urldecode($document),
            relationshipTypes: array_values(array_map('strval', (array) $request->query('relationship_types', []))),
            depth: (int) $request->query('depth', 1),
            limit: (int) $request->query('limit', 50),
            requestId: $this->requestId($request),
        ));
    }

    public function retrieve(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => ['required', 'string', 'min:2', 'max:500'],
            'profile' => ['sometimes', 'string'],
            'filters' => ['sometimes', 'array'],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'context_limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid knowledge retrieval request.', 400, $validator->errors());
        }

        return $this->respond(fn () => $this->knowledge->retrieve($validator->validated(), $this->requestId($request)));
    }

    public function answer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string', 'min:2', 'max:1000'],
            'profile' => ['sometimes', 'string'],
            'filters' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid knowledge answer request.', 400, $validator->errors());
        }

        return $this->respond(
            fn () => $this->knowledge->answer($validator->validated(), $this->requestId($request)),
            'Sorry, I couldn\'t generate an answer right now. Please try again.',
        );
    }

    public function agent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'input' => ['required', 'string', 'min:2', 'max:1000'],
            'profile' => ['sometimes', 'string'],
            'filters' => ['sometimes', 'array'],
            'allowed_tools' => ['sometimes', 'array'],
            'allowed_tools.*' => ['string'],
            'max_steps' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid knowledge agent request.', 400, $validator->errors());
        }

        return $this->respond(fn () => $this->knowledge->runAgent($validator->validated(), $this->requestId($request)));
    }

    public function agentExecution(Request $request, string $id): JsonResponse
    {
        return $this->respond(fn () => $this->knowledge->agentExecution($id, $this->requestId($request)));
    }

    public function replayAgentExecution(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'strict' => ['sometimes', 'boolean'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid knowledge agent replay request.', 400, $validator->errors());
        }

        return $this->respond(fn () => $this->knowledge->replayAgentExecution($id, $validator->validated(), $this->requestId($request)));
    }

    public function agentReplay(Request $request, string $id): JsonResponse
    {
        return $this->respond(fn () => $this->knowledge->agentReplay($id, $this->requestId($request)));
    }

    private function respond(callable $operation, ?string $serviceFailureMessage = null): JsonResponse
    {
        try {
            return ApiResponse::success($operation()->toArray());
        } catch (KnowledgeServiceException $exception) {
            $status = $this->publicStatus($exception->statusCode);

            return ApiResponse::error(
                message: $status >= 500 && $serviceFailureMessage !== null ? $serviceFailureMessage : $exception->getMessage(),
                status: $status,
                errors: $status >= 500 && $serviceFailureMessage !== null ? [] : $exception->errors,
            );
        }
    }

    private function requestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-ID');

        return is_string($header) && $header !== '' ? $header : (string) Str::uuid();
    }

    private function publicStatus(int $status): int
    {
        return match (true) {
            $status === 404 => 404,
            $status === 409 => 409,
            $status === 422 || $status === 400 => 400,
            $status === 429 => 429,
            default => 503,
        };
    }
}
