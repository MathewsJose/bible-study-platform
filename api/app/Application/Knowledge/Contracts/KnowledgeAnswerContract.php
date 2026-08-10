<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\KnowledgeServiceResult;

interface KnowledgeAnswerContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function answer(array $payload, ?string $requestId = null): KnowledgeServiceResult;
}
