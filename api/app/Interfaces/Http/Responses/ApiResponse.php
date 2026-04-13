<?php

namespace App\Interfaces\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\MessageBag;

class ApiResponse
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status, array|MessageBag $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors instanceof MessageBag ? $errors->toArray() : $errors,
        ], $status);
    }
}
