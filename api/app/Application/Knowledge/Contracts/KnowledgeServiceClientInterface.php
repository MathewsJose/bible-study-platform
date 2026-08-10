<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

interface KnowledgeServiceClientInterface extends KnowledgeAgentContract, KnowledgeAnswerContract, KnowledgeRetrievalContract, KnowledgeSearchContract, ReferenceResolutionContract
{
    public function health(?string $requestId = null): bool;
}
