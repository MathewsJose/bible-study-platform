<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Contracts;

use App\Application\Knowledge\Importing\DTOs\NormalizedKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;

interface DocumentNormalizerInterface
{
    /**
     * @return list<NormalizedKnowledgeDocument>
     */
    public function normalize(RawKnowledgeDocument $rawDocument): array;
}
