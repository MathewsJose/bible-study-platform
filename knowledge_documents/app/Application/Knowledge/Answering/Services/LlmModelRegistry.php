<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

final readonly class LlmModelRegistry
{
    /** @return array<string, mixed> */
    public function model(string $provider, string $model): array
    {
        $configured = config("llm.models.{$provider}:{$model}");

        if (is_array($configured)) {
            return $configured;
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'capabilities' => [
                'json' => null,
                'tools' => null,
                'streaming' => null,
            ],
            'context_window' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function capabilities(string $provider, string $model): array
    {
        $modelConfig = $this->model($provider, $model);

        return is_array($modelConfig['capabilities'] ?? null) ? $modelConfig['capabilities'] : [];
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_values(array_map(
            static fn (array $model): array => $model,
            (array) config('llm.models', []),
        ));
    }
}
