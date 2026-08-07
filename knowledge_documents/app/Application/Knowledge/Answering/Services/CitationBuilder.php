<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

use App\Application\Knowledge\Answering\DTOs\CitationData;
use App\Application\Knowledge\Retrieval\DTOs\RetrievalResult;

final readonly class CitationBuilder
{
    /** @return list<CitationData> */
    public function build(RetrievalResult $retrieval): array
    {
        $citations = [];

        foreach ($retrieval->context as $index => $contextDocument) {
            $document = $contextDocument->candidate->document;
            $citations[] = new CitationData(
                number: $index + 1,
                documentId: $document->id,
                reference: $document->reference,
                title: $document->title,
                sourceType: $document->sourceType,
                sourceName: $document->sourceName,
                retrievalScore: $contextDocument->candidate->score,
                metadata: [
                    'stages' => $contextDocument->candidate->stages,
                    'relationship_path' => $contextDocument->candidate->relationshipPath,
                ],
            );
        }

        return $citations;
    }
}
