<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;

interface ResultFusionStrategyInterface
{
    /**
     * @param  list<RankedKnowledgeDocumentData>  $vectorResults
     * @param  list<RankedKnowledgeDocumentData>  $lexicalResults
     * @return list<HybridRankedKnowledgeDocumentData>
     */
    public function fuse(array $vectorResults, array $lexicalResults, int $topK, float $minimumScore = 0.0): array;
}
