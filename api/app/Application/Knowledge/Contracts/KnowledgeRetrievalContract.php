<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\KnowledgeServiceResult;

interface KnowledgeRetrievalContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function retrieve(array $payload, ?string $requestId = null): KnowledgeServiceResult;

    /**
     * @param  list<string>  $relationshipTypes
     */
    public function related(string $document, array $relationshipTypes = [], int $depth = 1, int $limit = 50, ?string $requestId = null): KnowledgeServiceResult;
}
