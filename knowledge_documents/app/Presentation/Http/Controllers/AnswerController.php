<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Knowledge\Answering\Services\AnswerQuestionService;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\AnswerQuestionRequest;
use Illuminate\Http\JsonResponse;

final class AnswerController extends Controller
{
    public function __construct(private readonly AnswerQuestionService $answers) {}

    public function store(AnswerQuestionRequest $request): JsonResponse
    {
        $history = array_values(array_filter(
            (array) $request->validated('history', []),
            static fn (mixed $message): bool => is_array($message)
                && is_string($message['role'] ?? null)
                && is_string($message['content'] ?? null),
        ));

        $answer = $this->answers->answer(
            question: (string) $request->string('question'),
            profile: $request->validated('profile') ?? 'ai_answer',
            filters: (array) $request->validated('filters', []),
            history: $history,
        );

        return response()->json(['data' => $answer->toArray()]);
    }
}
