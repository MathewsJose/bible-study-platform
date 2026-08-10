<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Observability\Services;

use App\Application\Knowledge\Agents\DTOs\ToolResult;

final readonly class FailureClassifier
{
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const TOOL_ERROR = 'TOOL_ERROR';
    public const TIMEOUT = 'TIMEOUT';
    public const PROVIDER_ERROR = 'PROVIDER_ERROR';
    public const RETRIEVAL_ERROR = 'RETRIEVAL_ERROR';
    public const STEP_LIMIT = 'STEP_LIMIT';
    public const LOOP_DETECTED = 'LOOP_DETECTED';
    public const AUTHORIZATION_ERROR = 'AUTHORIZATION_ERROR';
    public const UNKNOWN = 'UNKNOWN';

    public function classifyStatus(string $status): string
    {
        return match ($status) {
            'guardrail_violation' => self::AUTHORIZATION_ERROR,
            'max_steps_reached' => self::STEP_LIMIT,
            'timeout' => self::TIMEOUT,
            default => self::UNKNOWN,
        };
    }

    public function classifyToolResult(ToolResult $result): string
    {
        $message = mb_strtolower($result->error ?? implode(' ', $result->warnings));

        return match (true) {
            $result->status === 'guardrail_violation' => self::AUTHORIZATION_ERROR,
            str_contains($message, 'validation') => self::VALIDATION_ERROR,
            str_contains($message, 'provider') => self::PROVIDER_ERROR,
            str_contains($message, 'retrieval') || str_contains($message, 'embedding') => self::RETRIEVAL_ERROR,
            str_contains($message, 'timeout') => self::TIMEOUT,
            default => self::TOOL_ERROR,
        };
    }
}
