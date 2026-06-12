<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Application\Knowledge\Services\SearchKnowledgeDocumentsService;
use App\Application\Knowledge\Services\SemanticSearchService;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\ListKnowledgeDocumentsRequest;
use App\Presentation\Http\Requests\SearchKnowledgeDocumentsRequest;
use App\Presentation\Http\Requests\StoreKnowledgeDocumentRequest;
use App\Presentation\Http\Requests\UpdateKnowledgeDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class KnowledgeDocumentController extends Controller
{
    public function __construct(
        private readonly KnowledgeDocumentService $documents,
        private readonly SearchKnowledgeDocumentsService $search,
        private readonly SemanticSearchService $semanticSearch,
    ) {}

    public function index(ListKnowledgeDocumentsRequest $request): JsonResponse
    {
        $filters = $request->safe()->except('per_page');
        $perPage = (int) $request->integer('per_page', 15);
        $documents = $this->documents->paginate($filters, $perPage);

        return response()->json([
            'data' => $documents->getCollection()
                ->map(fn (KnowledgeDocumentData $document): array => $document->toArray())
                ->values(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    public function store(StoreKnowledgeDocumentRequest $request): JsonResponse
    {
        $document = $this->documents->create($request->validated());

        return response()->json(['data' => $document->toArray()], Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->documents->get($id)->toArray()]);
    }

    public function update(UpdateKnowledgeDocumentRequest $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->documents->update($id, $request->validated())->toArray()]);
    }

    public function destroy(string $id): Response
    {
        $this->documents->delete($id);

        return response()->noContent();
    }

    public function fullTextSearch(SearchKnowledgeDocumentsRequest $request): JsonResponse
    {
        $results = $this->search->fullText(
            query: (string) $request->string('query'),
            limit: (int) $request->integer('limit', 10),
        );

        return response()->json([
            'data' => array_map(
                static fn (RankedKnowledgeDocumentData $result): array => $result->toArray(),
                $results,
            ),
        ]);
    }

    public function semanticSearch(SearchKnowledgeDocumentsRequest $request): JsonResponse
    {
        $results = $this->semanticSearch->search(
            query: (string) $request->string('query'),
            limit: (int) $request->integer('limit', 10),
        );

        return response()->json([
            'data' => array_map(
                static fn (RankedKnowledgeDocumentData $result): array => $result->toArray(),
                $results,
            ),
        ]);
    }
}
