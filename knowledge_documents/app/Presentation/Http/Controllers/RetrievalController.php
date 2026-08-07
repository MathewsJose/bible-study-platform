<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\RetrieveContextRequest;
use Illuminate\Http\JsonResponse;

final class RetrievalController extends Controller
{
    public function __construct(private readonly RetrievalEngine $retrieval) {}

    public function store(RetrieveContextRequest $request): JsonResponse
    {
        $result = $this->retrieval->retrieve(
            query: (string) $request->string('query'),
            profile: $request->validated('profile'),
            filters: (array) $request->validated('filters', []),
            topK: $request->validated('top_k') === null ? null : (int) $request->validated('top_k'),
            contextLimit: $request->validated('context_limit') === null ? null : (int) $request->validated('context_limit'),
            includeExplanations: (bool) $request->boolean('include_explanations', true),
        );

        return response()->json(['data' => $result->toArray()]);
    }
}
