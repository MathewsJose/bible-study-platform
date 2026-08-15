<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Answering\Services\LlmModelRouter;
use App\Application\Knowledge\Answering\Services\LlmProviderHealthService;
use Illuminate\Console\Command;

final class AiLlmHealthCommand extends Command
{
    protected $signature = 'ai:llm-health {--task=answer_generation : LLM task to inspect} {--profile= : Optional model profile override} {--format=table : table or json}';

    protected $description = 'Show active LLM gateway configuration, capabilities, and provider policy status.';

    public function handle(LlmModelRouter $router, LlmProviderHealthService $health): int
    {
        $task = (string) $this->option('task');
        $profile = $this->option('profile');
        $selection = $router->select($task, is_string($profile) && $profile !== '' ? $profile : null);
        $providers = collect($health->check())->keyBy('provider');
        $providerHealth = $providers->get($selection->provider);

        $payload = [
            'task' => $selection->task,
            'profile' => $selection->profileName,
            'provider' => $selection->provider,
            'model' => $selection->model,
            'fallback_profile' => $selection->fallbackProfile,
            'capabilities' => $selection->capabilities,
            'external_processing' => (bool) config('ai_security.external_processing.allow', false),
            'provider_status' => $providerHealth?->status ?? 'UNKNOWN',
            'security_policy' => $providerHealth?->securityPolicy ?? 'unknown',
            'connectivity' => $providerHealth?->connectivity ?? 'not_checked',
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('LLM Gateway Health');
        $this->table(['Key', 'Value'], [
            ['Task', $payload['task']],
            ['Profile', $payload['profile']],
            ['Provider', $payload['provider']],
            ['Model', $payload['model']],
            ['Fallback profile', $payload['fallback_profile'] ?? '-'],
            ['Provider status', $payload['provider_status']],
            ['Security policy', $payload['security_policy']],
            ['External processing', $payload['external_processing'] ? 'allowed' : 'blocked'],
            ['Connectivity', $payload['connectivity']],
            ['Capabilities', json_encode($payload['capabilities'], JSON_THROW_ON_ERROR)],
        ]);

        return self::SUCCESS;
    }
}
