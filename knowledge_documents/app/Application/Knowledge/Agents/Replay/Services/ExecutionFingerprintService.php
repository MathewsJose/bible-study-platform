<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Replay\Services;

use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use App\Infrastructure\Knowledge\Agents\Persistence\AgentExecutionRecord;

final readonly class ExecutionFingerprintService
{
    public function __construct(
        private AgentToolRegistry $tools,
        private CorpusFingerprintService $corpus,
        private StableJsonHasher $hasher,
    ) {}

    /** @return array<string, mixed> */
    public function current(?AgentExecutionRecord $execution = null): array
    {
        return $this->forProfile($execution?->profile ?? (string) config('agents.default_profile', 'catholic_research'));
    }

    /** @return array<string, mixed> */
    public function forProfile(string $profile): array
    {
        $corpus = $this->corpus->fingerprint();
        $payload = [
            'agent_profile' => $profile,
            'agent_profile_config' => config('agents.profiles.'.$profile, []),
            'planner' => config('agents.planner', 'deterministic'),
            'tool_registry' => $this->toolRegistrySnapshot(),
            'retrieval_profiles' => config('retrieval.profiles', []),
            'ai' => [
                'provider' => config('ai.provider', 'null'),
                'model' => config('ai.model', 'null-answer-model'),
                'temperature' => config('ai.temperature', 0.0),
                'max_tokens' => config('ai.max_tokens', 800),
                'prompt' => config('ai.prompt', []),
            ],
            'llm' => [
                'default_provider' => config('llm.default_provider', config('ai.provider', 'null')),
                'default_model' => config('llm.default_model', config('ai.model', 'null-answer-model')),
                'routing' => config('llm.routing', []),
                'profiles' => config('llm.profiles', []),
            ],
            'corpus_hash' => $corpus['hash'],
            'app_version' => config('app.version', 'local'),
        ];

        return [
            'hash' => $this->hasher->hash($payload),
            'payload' => $payload,
            'corpus' => $corpus,
        ];
    }

    /** @return list<string> */
    private function toolRegistrySnapshot(): array
    {
        $names = $this->tools->names();
        sort($names);

        return $names;
    }
}
