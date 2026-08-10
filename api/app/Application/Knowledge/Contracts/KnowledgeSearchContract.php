<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\KnowledgeServiceResult;

interface KnowledgeSearchContract
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(string $query, array $filters = [], int $limit = 10, ?string $requestId = null): KnowledgeServiceResult;
}
