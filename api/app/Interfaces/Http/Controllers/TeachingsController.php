<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Teachings\Services\TeachingsService;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeachingsController extends Controller
{
    public function __construct(private readonly TeachingsService $teachingsService) {}

    public function show(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'book' => ['required', 'string'],
            'chapter' => ['required', 'integer', 'min:1'],
            'verse' => ['required', 'integer', 'min:1'],
            'language' => ['sometimes', 'string'],
            'version' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid teachings request.', 400, $validator->errors());
        }

        return ApiResponse::success($this->teachingsService->getTeachings(
            language: strtolower((string) $request->query('language', 'en')),
            version: strtolower((string) $request->query('version', 'nrsvce')),
            book: (string) $request->query('book'),
            chapter: (int) $request->query('chapter'),
            verse: (int) $request->query('verse')
        ));
    }
}
