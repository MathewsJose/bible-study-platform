<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Bible\UseCases\GetVerseUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class BibleController extends Controller
{
    public function __construct(private readonly GetVerseUseCase $useCase) {}

    public function show(Request $request, string $book, string $chapter, string $verse): JsonResponse
    {
        $verseDTO = $this->useCase->execute($book, (int) $chapter, (int) $verse);

        if (!$verseDTO) {
            return response()->json(['message' => 'Verse not found'], 404);
        }

        return response()->json($verseDTO);
    }
}
