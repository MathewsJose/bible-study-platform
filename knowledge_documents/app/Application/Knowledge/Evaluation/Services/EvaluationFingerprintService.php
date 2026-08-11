<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\Services;

use App\Application\Knowledge\Agents\Replay\Services\ExecutionFingerprintService;
use App\Application\Knowledge\Agents\Replay\Services\StableJsonHasher;

final readonly class EvaluationFingerprintService
{
    public function __construct(
        private ExecutionFingerprintService $execution,
        private StableJsonHasher $hasher,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(string $profile): array
    {
        $execution = $this->execution->forProfile($profile);
        $security = [
            'enabled' => config('ai_security.enabled'),
            'pii_action' => config('ai_security.pii.action'),
            'prompt_injection_action' => config('ai_security.prompt_injection.action'),
            'external_processing' => config('ai_security.external_processing.allow'),
            'limits' => config('ai_security.limits', []),
        ];
        $payload = [
            'execution' => $execution['payload'] ?? [],
            'corpus' => $execution['corpus'] ?? [],
            'security' => $security,
            'evaluation' => config('evaluation', []),
        ];

        return [
            'hash' => $this->hasher->hash($payload),
            'execution_hash' => $execution['hash'] ?? null,
            'corpus' => $execution['corpus'] ?? [],
            'security_hash' => $this->hasher->hash($security),
            'payload' => $payload,
        ];
    }
}
