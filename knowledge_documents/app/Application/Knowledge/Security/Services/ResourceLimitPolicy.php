<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

final readonly class ResourceLimitPolicy
{
    /** @return list<string> */
    public function violationsForInput(string $input): array
    {
        $max = (int) config('ai_security.limits.max_input_characters', 1000);

        return mb_strlen($input) > $max ? ['RESOURCE_LIMIT_EXCEEDED: input exceeds '.$max.' characters.'] : [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<string>
     */
    public function violationsForToolArguments(array $arguments): array
    {
        $violations = [];

        foreach (['query', 'question', 'input', 'reference', 'document_id'] as $key) {
            if (is_string($arguments[$key] ?? null)) {
                $violations = [...$violations, ...$this->violationsForInput((string) $arguments[$key])];
            }
        }

        $topK = $arguments['top_k'] ?? $arguments['limit'] ?? null;
        if (is_numeric($topK) && (int) $topK > (int) config('ai_security.limits.max_retrieval_top_k', 50)) {
            $violations[] = 'RESOURCE_LIMIT_EXCEEDED: retrieval limit is too large.';
        }

        return $violations;
    }

    /** @param list<array{role: string, content: string}> $messages */
    public function violationsForMessages(array $messages): array
    {
        $violations = [];
        $max = (int) config('ai_security.limits.max_history_message_characters', 3000);

        foreach ($messages as $message) {
            if (mb_strlen($message['content']) > $max) {
                $violations[] = 'RESOURCE_LIMIT_EXCEEDED: message content exceeds '.$max.' characters.';
            }
        }

        return array_values(array_unique($violations));
    }
}
