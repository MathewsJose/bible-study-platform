<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Conversation;

interface MemoryProviderInterface
{
    public function remember(string $sessionId, string $role, string $content): void;

    /** @return list<array{role: string, content: string}> */
    public function recall(string $sessionId): array;
}
