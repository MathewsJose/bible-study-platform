<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

final readonly class AgentProfile
{
    /**
     * @param  list<string>  $allowedTools
     */
    public function __construct(
        public string $identifier,
        public string $displayName,
        public array $allowedTools,
        public int $maxSteps,
        public int $maxToolCalls,
        public int $timeoutSeconds,
        public string $retrievalProfile,
        public string $answerProfile,
        public string $systemInstructions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromConfig(string $identifier, array $data): self
    {
        return new self(
            identifier: $identifier,
            displayName: (string) ($data['display_name'] ?? $identifier),
            allowedTools: array_values(array_map('strval', (array) ($data['allowed_tools'] ?? []))),
            maxSteps: max(1, (int) ($data['max_steps'] ?? config('agents.defaults.max_steps', 8))),
            maxToolCalls: max(1, (int) ($data['max_tool_calls'] ?? config('agents.defaults.max_tool_calls', 8))),
            timeoutSeconds: max(1, (int) ($data['timeout_seconds'] ?? config('agents.defaults.timeout_seconds', 30))),
            retrievalProfile: (string) ($data['retrieval_profile'] ?? 'ai_answer'),
            answerProfile: (string) ($data['answer_profile'] ?? 'ai_answer'),
            systemInstructions: (string) ($data['system_instructions'] ?? ''),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'display_name' => $this->displayName,
            'allowed_tools' => $this->allowedTools,
            'max_steps' => $this->maxSteps,
            'max_tool_calls' => $this->maxToolCalls,
            'timeout_seconds' => $this->timeoutSeconds,
            'retrieval_profile' => $this->retrievalProfile,
            'answer_profile' => $this->answerProfile,
        ];
    }
}
