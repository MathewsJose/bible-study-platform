<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

final readonly class ProviderPolicy
{
    public function externalProcessingAllowed(string $provider): bool
    {
        if (
            app()->runningUnitTests()
            && config('ai.provider', 'null') === 'null'
            && ! in_array($provider, ['openai', 'gemini', 'claude'], true)
        ) {
            return true;
        }

        if ((bool) config('ai_security.external_processing.allow', false)) {
            return true;
        }

        return in_array($provider, (array) config('ai_security.external_processing.local_providers', []), true);
    }
}
