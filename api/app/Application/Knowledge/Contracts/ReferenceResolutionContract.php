<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\KnowledgeServiceResult;

interface ReferenceResolutionContract
{
    public function resolveReference(string $reference, ?string $requestId = null): KnowledgeServiceResult;
}
