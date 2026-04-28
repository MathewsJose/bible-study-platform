<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\History\Services\HistoryService;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HistoryController extends Controller
{
    public function __construct(private readonly HistoryService $historyService) {}

    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'book' => ['required', 'string'],
            'chapter' => ['required', 'integer', 'min:1'],
            'verse' => ['nullable', 'integer', 'min:1'],
            'language' => ['sometimes', 'string'],
            'version' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid historical context request.', 400, $validator->errors());
        }

        return ApiResponse::success($this->historyService->getHistoricalContext(
            language: strtolower((string) $request->query('language', 'en')),
            version: strtolower((string) $request->query('version', 'nrsvce')),
            book: (string) $request->query('book'),
            chapter: (int) $request->query('chapter'),
            verse: $request->query->has('verse') ? (int) $request->query('verse') : null
        ));
    }
}
