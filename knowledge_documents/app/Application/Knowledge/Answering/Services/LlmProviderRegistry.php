<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\Contracts\LLMProviderInterface;
use App\Application\Knowledge\Answering\Exceptions\LlmConfigurationException;
use App\Infrastructure\Knowledge\AI\AnthropicProvider;
use App\Infrastructure\Knowledge\AI\GoogleProvider;
use App\Infrastructure\Knowledge\AI\LocalProvider;
use App\Infrastructure\Knowledge\AI\NullProvider;
use App\Infrastructure\Knowledge\AI\OllamaProvider;
use App\Infrastructure\Knowledge\AI\OpenAIProvider;
use Illuminate\Contracts\Container\Container;

final readonly class LlmProviderRegistry
{
    public function __construct(
        private Container $container,
        private LLMProviderInterface $defaultProvider,
    ) {}

    public function provider(string $provider): LLMProviderInterface
    {
        if ($provider === $this->defaultProvider->identifier()) {
            return $this->defaultProvider;
        }

        $class = $this->providerClass($provider);

        return $this->container->make($class);
    }

    /** @return list<string> */
    public function providers(): array
    {
        $providers = array_keys((array) config('llm.providers', []));
        sort($providers);

        return array_values(array_map('strval', $providers));
    }

    /** @return class-string<LLMProviderInterface> */
    private function providerClass(string $provider): string
    {
        return match ($provider) {
            'null' => NullProvider::class,
            'local' => LocalProvider::class,
            'ollama' => OllamaProvider::class,
            'openai' => OpenAIProvider::class,
            'anthropic', 'claude' => AnthropicProvider::class,
            'google', 'gemini' => GoogleProvider::class,
            default => throw new LlmConfigurationException('LLM provider is not registered.', $provider),
        };
    }
}
