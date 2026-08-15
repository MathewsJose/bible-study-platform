<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Models\AiAnswerFeedback;
use App\Models\User;

final readonly class AiAnswerFeedbackService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(User $user, array $data): AiAnswerFeedback
    {
        $payload = [
            'answer_execution_id' => $this->optionalString($data['answer_execution_id'] ?? null),
            'rating' => (string) $data['rating'],
            'reason' => $this->optionalString($data['reason'] ?? null),
            'comment' => $this->comment($data['comment'] ?? null),
            'provider' => $this->optionalString($data['provider'] ?? null),
            'model' => $this->optionalString($data['model'] ?? null),
            'retrieval_strategy' => $this->optionalString($data['retrieval_strategy'] ?? null),
            'source_count' => $data['source_count'] ?? null,
            'citation_count' => $data['citation_count'] ?? null,
            'metadata' => $this->metadata($data),
        ];

        return AiAnswerFeedback::query()->updateOrCreate(
            ['user_id' => $user->id, 'request_id' => (string) $data['request_id']],
            $payload,
        );
    }

    private function comment(mixed $comment): ?string
    {
        if (! (bool) config('knowledge_service.feedback_store_comments', false)) {
            return null;
        }

        if (! is_string($comment) || trim($comment) === '') {
            return null;
        }

        return mb_substr(trim($comment), 0, 1000);
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function metadata(array $data): array
    {
        return array_filter([
            'client_surface' => $this->optionalString($data['client_surface'] ?? null),
            'answer_status' => $this->optionalString($data['answer_status'] ?? null),
            'latency_ms' => $data['latency_ms'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
