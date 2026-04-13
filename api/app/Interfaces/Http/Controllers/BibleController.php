<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Bible\Services\BibleService;
use App\Application\Bible\UseCases\GetVerseUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Validator;

class BibleController extends Controller
{
    public function __construct(
        private readonly GetVerseUseCase $useCase,
        private readonly BibleService $bibleService
    ) {}

    public function chapter(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'book' => ['required', 'string'],
            'chapter' => ['required', 'integer', 'min:1'],
            'language' => ['sometimes', 'string'],
            'version' => ['sometimes', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Invalid bible chapter request.', 400, $validator->errors());
        }

        $chapter = $this->bibleService->getBibleChapter(
            language: strtolower((string) $request->query('language', 'en')),
            version: strtolower((string) $request->query('version', 'nrsvce')),
            book: (string) $request->query('book'),
            chapter: (int) $request->query('chapter')
        );

        if ($chapter === null) {
            return ApiResponse::error('Bible chapter not found.', 404);
        }

        return ApiResponse::success($chapter);
    }

    public function show(Request $request, string $book, string $chapter, string $verse): JsonResponse
    {
        $verseDTO = $this->useCase->execute($book, (int) $chapter, (int) $verse);

        if (!$verseDTO) {
            return ApiResponse::error('Verse not found.', 404);
        }

        return ApiResponse::success($verseDTO);
    }
}
