<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers\Knowledge;

use App\Application\Knowledge\Services\AiAnswerFeedbackService;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class AiAnswerFeedbackController extends Controller
{
    public function __construct(private readonly AiAnswerFeedbackService $feedback) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_id' => ['required', 'string', 'max:120'],
            'answer_execution_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'rating' => ['required', 'string', Rule::in(['helpful', 'not_helpful'])],
            'reason' => ['sometimes', 'nullable', 'string', Rule::in(['incorrect_answer', 'incorrect_citation', 'missing_information', 'other'])],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:80'],
            'model' => ['sometimes', 'nullable', 'string', 'max:120'],
            'retrieval_strategy' => ['sometimes', 'nullable', 'string', 'max:80'],
            'source_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'citation_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'client_surface' => ['sometimes', 'nullable', 'string', 'max:80'],
            'answer_status' => ['sometimes', 'nullable', 'string', 'max:80'],
            'latency_ms' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid AI answer feedback request.', 400, $validator->errors());
        }

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $record = $this->feedback->record($user, $validator->validated());

        return ApiResponse::success([
            'id' => $record->id,
            'request_id' => $record->request_id,
            'rating' => $record->rating,
            'reason' => $record->reason,
            'comment_stored' => $record->comment !== null,
        ], $record->wasRecentlyCreated ? 201 : 200);
    }
}
