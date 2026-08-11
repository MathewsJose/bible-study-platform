<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\DTOs\LlmProviderHealth;
use App\Application\Knowledge\Security\Contracts\AISecurityPolicyInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class LlmProviderHealthService
{
    public function __construct(
        private LlmProviderRegistry $providers,
        private AISecurityPolicyInterface $security,
    ) {}

    /** @return list<LlmProviderHealth> */
    public function check(): array
    {
        return array_map(fn (string $provider): LlmProviderHealth => $this->checkProvider($provider), $this->providers->providers());
    }

    private function checkProvider(string $provider): LlmProviderHealth
    {
        $config = (array) config("llm.providers.{$provider}", []);
        $model = is_string($config['model'] ?? null) ? $config['model'] : null;
        $security = $this->security->evaluateProvider($provider, [], ['surface' => 'provider_health']);

        if (! $security->allowed) {
            return new LlmProviderHealth($provider, 'BLOCKED', $model, 'not_checked', 'blocked');
        }

        if (($config['enabled'] ?? true) === false) {
            return new LlmProviderHealth($provider, 'DISABLED', $model, 'not_checked', 'allowed');
        }

        if (in_array($provider, ['null'], true)) {
            return new LlmProviderHealth($provider, 'OK', $model, 'local_deterministic', 'allowed');
        }

        $apiKey = (string) ($config['api_key'] ?? '');
        $category = (string) ($config['category'] ?? '');
        if (! in_array($category, ['local'], true) && $apiKey === '') {
            return new LlmProviderHealth($provider, 'NOT CONFIGURED', $model, 'missing_api_key', 'allowed');
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $healthPath = (string) ($config['health_path'] ?? '');
        if ($baseUrl === '' || $healthPath === '') {
            return new LlmProviderHealth($provider, 'CONFIGURED', $model, 'not_checked', 'allowed');
        }

        try {
            $response = Http::timeout(2)->connectTimeout(1)->get($baseUrl.$healthPath);
        } catch (Throwable) {
            return new LlmProviderHealth($provider, 'FAILED', $model, 'connection_failed', 'allowed');
        }

        return new LlmProviderHealth(
            provider: $provider,
            status: $response->successful() ? 'OK' : 'FAILED',
            model: $model,
            connectivity: (string) $response->status(),
            securityPolicy: 'allowed',
        );
    }
}
