<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Conversation;

interface SessionContextInterface
{
    public function sessionId(): ?string;

    public function userId(): ?string;
}
