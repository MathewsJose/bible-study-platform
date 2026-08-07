<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\AI;

final class ClaudeProvider extends OpenAIProvider
{
    public function identifier(): string
    {
        return 'claude';
    }

    protected function providerKey(): string
    {
        return 'claude';
    }
}
