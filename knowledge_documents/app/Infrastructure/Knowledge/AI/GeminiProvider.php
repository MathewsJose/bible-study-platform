<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\AI;

final class GeminiProvider extends OpenAIProvider
{
    public function identifier(): string
    {
        return 'gemini';
    }

    protected function providerKey(): string
    {
        return 'gemini';
    }
}
