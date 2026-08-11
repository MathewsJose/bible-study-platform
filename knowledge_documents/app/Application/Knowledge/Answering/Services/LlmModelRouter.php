<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\DTOs\LlmModelSelection;

final readonly class LlmModelRouter
{
    public function __construct(private LlmModelRegistry $models) {}

    public function select(string $task, ?string $profile = null): LlmModelSelection
    {
        $profileName = $profile ?: (string) config("llm.routing.{$task}", 'fast_local');
        $profileConfig = (array) config("llm.profiles.{$profileName}", []);

        if ($profileConfig === []) {
            $profileName = 'fast_local';
            $profileConfig = (array) config('llm.profiles.fast_local', []);
        }

        $provider = (string) ($profileConfig['provider'] ?? config('llm.default_provider', config('ai.provider', 'null')));
        $model = (string) ($profileConfig['model'] ?? config("llm.providers.{$provider}.model", config('llm.default_model', config('ai.model', 'null-answer-model'))));
        $capabilities = $this->models->capabilities($provider, $model);

        return new LlmModelSelection(
            task: $task,
            profileName: $profileName,
            provider: $provider,
            model: $model,
            profile: $profileConfig,
            capabilities: $capabilities,
            fallbackProfile: is_string($profileConfig['fallback'] ?? null) ? $profileConfig['fallback'] : null,
        );
    }
}
