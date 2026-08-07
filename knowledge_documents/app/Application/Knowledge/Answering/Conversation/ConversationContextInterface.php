<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Conversation;

interface ConversationContextInterface
{
    /** @return list<array{role: string, content: string}> */
    public function messages(): array;
}
