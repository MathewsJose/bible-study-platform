<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Knowledge\Services\RetrievalEvaluationService;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\RunRetrievalEvaluationRequest;
use Illuminate\Http\JsonResponse;

final class RetrievalEvaluationController extends Controller
{
    public function __construct(private readonly RetrievalEvaluationService $evaluations) {}

    public function store(RunRetrievalEvaluationRequest $request): JsonResponse
    {
        $summary = $this->evaluations->evaluate([
            'topK' => (int) $request->integer('top_k', 5),
            'minimumScore' => $request->validated('minimum_score'),
            'questionId' => $request->validated('question_id'),
            'category' => $request->validated('category'),
            'limit' => $request->validated('limit'),
            'save' => (bool) $request->boolean('save'),
        ]);

        return response()->json(['data' => $summary->toArray()]);
    }
}
