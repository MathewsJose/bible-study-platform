<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Knowledge\Agents\Contracts\AgentInterface;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Answering\Services\AnswerQuestionService;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Integration\DTOs\KnowledgeDocumentSummary;
use App\Application\Knowledge\Integration\Services\ReferenceResolutionService;
use App\Application\Knowledge\Integration\Services\RelatedKnowledgeIntegrationService;
use App\Application\Knowledge\Agents\Observability\Contracts\AgentTraceRepositoryInterface;
use App\Application\Knowledge\Agents\Replay\Services\AgentReplayService;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use App\Application\Knowledge\Security\Contracts\AISecurityPolicyInterface;
use App\Application\Knowledge\Security\Exceptions\AISecurityException;
use App\Application\Knowledge\Services\SearchKnowledgeDocumentsService;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentReplayRecord;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\AnswerQuestionRequest;
use App\Presentation\Http\Requests\KnowledgeIntegrationSearchRequest;
use App\Presentation\Http\Requests\KnowledgeRelatedRequest;
use App\Presentation\Http\Requests\ReplayAgentExecutionRequest;
use App\Presentation\Http\Requests\RetrieveContextRequest;
use App\Presentation\Http\Requests\RunAgentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

final class KnowledgeIntegrationController extends Controller
{
    public function __construct(
        private readonly SearchKnowledgeDocumentsService $search,
        private readonly ReferenceResolutionService $references,
        private readonly RelatedKnowledgeIntegrationService $related,
        private readonly RetrievalEngine $retrieval,
        private readonly AnswerQuestionService $answers,
        private readonly AgentInterface $agent,
        private readonly AgentTraceRepositoryInterface $traces,
        private readonly AgentReplayService $replays,
        private readonly AISecurityPolicyInterface $security,
    ) {}

    public function search(KnowledgeIntegrationSearchRequest $request): JsonResponse
    {
        $security = $this->security->evaluateInput((string) $request->string('query'), ['surface' => 'knowledge_search']);

        if (! $security->allowed) {
            throw new AISecurityException($security->errorCode, $security->message);
        }

        $filters = $request->safe()->only(['source_type', 'book', 'chapter', 'translation', 'language', 'tradition']);
        $results = $this->search->fullText(
            query: $security->safeInput,
            limit: (int) $request->integer('limit', 10),
            filters: $filters,
        );

        return response()->json([
            'data' => [
                'query' => $security->safeInput,
                'results' => array_map(
                    static fn (RankedKnowledgeDocumentData $result): array => KnowledgeDocumentSummary::fromDocument($result->document, $result->score)->toArray(),
                    $results,
                ),
            ],
            'meta' => [
                'limit' => (int) $request->integer('limit', 10),
                'total' => count($results),
            ],
        ]);
    }

    public function reference(string $reference): JsonResponse
    {
        $document = $this->references->resolve(urldecode($reference));

        if ($document === null) {
            return response()->json([
                'message' => 'Reference not found.',
                'errors' => ['reference' => ['No knowledge document matched the supplied reference.']],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => ['document' => $document->toArray()]]);
    }

    public function related(KnowledgeRelatedRequest $request, string $document): JsonResponse
    {
        $result = $this->related->related(
            document: urldecode($document),
            relationshipTypes: array_values(array_map('strval', (array) $request->validated('relationship_types', []))),
            depth: (int) $request->integer('depth', 1),
            limit: (int) $request->integer('limit', 50),
        );

        if ($result === null) {
            return response()->json([
                'message' => 'Reference not found.',
                'errors' => ['document' => ['No knowledge document matched the supplied reference or id.']],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $result]);
    }

    public function retrieve(RetrieveContextRequest $request): JsonResponse
    {
        $security = $this->security->evaluateInput((string) $request->string('query'), ['surface' => 'knowledge_retrieve']);

        if (! $security->allowed) {
            throw new AISecurityException($security->errorCode, $security->message);
        }

        $result = $this->retrieval->retrieve(
            query: $security->safeInput,
            profile: $request->validated('profile'),
            filters: (array) $request->validated('filters', []),
            topK: $request->validated('top_k') === null ? null : (int) $request->validated('top_k'),
            contextLimit: $request->validated('context_limit') === null ? null : (int) $request->validated('context_limit'),
            includeExplanations: false,
        );

        return response()->json(['data' => $result->toArray()]);
    }

    public function answer(AnswerQuestionRequest $request): JsonResponse
    {
        $answer = $this->answers->answer(
            question: (string) $request->string('question'),
            profile: $request->validated('profile') ?? 'ai_answer',
            filters: (array) $request->validated('filters', []),
        );

        return response()->json(['data' => $answer->toArray()]);
    }

    public function agent(RunAgentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $response = $this->agent->execute(new AgentRequest(
            input: (string) $validated['input'],
            profile: (string) ($validated['profile'] ?? config('agents.default_profile', 'catholic_research')),
            filters: (array) ($validated['filters'] ?? []),
            allowedTools: array_values(array_map('strval', (array) ($validated['allowed_tools'] ?? []))),
            maxSteps: isset($validated['max_steps']) ? (int) $validated['max_steps'] : null,
            timeoutSeconds: isset($validated['timeout_seconds']) ? (int) $validated['timeout_seconds'] : null,
            metadata: (array) ($validated['metadata'] ?? []),
            requestId: (string) ($request->header('X-Request-ID') ?: ''),
        ));

        $data = $response->toArray();
        $data['trace'] = array_map(static fn (array $entry): array => [
            'event' => $entry['event'],
            'status' => $entry['status'],
            'step' => $entry['step'],
            'tool' => $entry['tool'],
            'latency_ms' => $entry['latency_ms'],
        ], $data['trace']);

        return response()->json(['data' => $data]);
    }

    public function execution(Request $request, string $id): JsonResponse
    {
        if (! $this->authorizedTraceRequest($request)) {
            return response()->json(['message' => 'Unauthorized trace inspection request.'], Response::HTTP_FORBIDDEN);
        }

        $trace = $this->traces->find($id);

        if ($trace === null) {
            return response()->json([
                'message' => 'Agent execution trace not found.',
                'errors' => ['id' => ['No persisted agent execution matched the supplied id.']],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $trace->toArray()]);
    }

    public function replay(ReplayAgentExecutionRequest $request, string $id): JsonResponse
    {
        if (! $this->authorizedTraceRequest($request)) {
            return response()->json(['message' => 'Unauthorized replay request.'], Response::HTTP_FORBIDDEN);
        }

        if (! $request->boolean('dry_run') && ! (bool) config('agent_observability.replay.allow_http_live_replay', true)) {
            return response()->json(['message' => 'Live replay is disabled for HTTP requests.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $replay = $this->replays->replay(
                executionId: $id,
                strict: (bool) $request->boolean('strict'),
                dryRun: (bool) $request->boolean('dry_run'),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['id' => [$exception->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->replayData($replay)], $replay->status === 'failed' ? Response::HTTP_CONFLICT : Response::HTTP_ACCEPTED);
    }

    public function replayStatus(Request $request, string $id): JsonResponse
    {
        if (! $this->authorizedTraceRequest($request)) {
            return response()->json(['message' => 'Unauthorized replay inspection request.'], Response::HTTP_FORBIDDEN);
        }

        $replay = AgentReplayRecord::query()->find($id);

        if (! $replay instanceof AgentReplayRecord) {
            return response()->json([
                'message' => 'Agent replay not found.',
                'errors' => ['id' => ['No persisted replay matched the supplied id.']],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $this->replayData($replay)]);
    }

    private function authorizedTraceRequest(Request $request): bool
    {
        $token = (string) config('agent_observability.trace_api.token', '');

        return $token === '' || $request->bearerToken() === $token;
    }

    /** @return array<string, mixed> */
    private function replayData(AgentReplayRecord $replay): array
    {
        return [
            'id' => $replay->id,
            'original_execution_id' => $replay->original_execution_id,
            'replay_execution_id' => $replay->replay_execution_id,
            'mode' => $replay->mode,
            'status' => $replay->status,
            'comparison_status' => $replay->comparison_status,
            'strict' => $replay->strict,
            'dry_run' => $replay->dry_run,
            'duration_ms' => $replay->duration_ms,
            'divergence_summary' => $replay->divergence_summary,
            'comparison' => $replay->comparison,
            'error_information' => $replay->error_information,
        ];
    }
}
