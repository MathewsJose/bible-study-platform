<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Graph\Contracts;

use App\Application\Knowledge\Graph\DTOs\ResolvedRelationship;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

interface ReferenceResolverInterface
{
    public function identifier(): string;

    /** @return list<ResolvedRelationship> */
    public function resolve(KnowledgeDocumentRecord $document): array;

    /** @return list<string> */
    public function unresolvedReferences(KnowledgeDocumentRecord $document): array;
}
