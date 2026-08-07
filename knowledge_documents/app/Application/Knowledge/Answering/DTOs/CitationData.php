<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class CitationData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $number,
        public string $documentId,
        public string $reference,
        public string $title,
        public string $sourceType,
        public string $sourceName,
        public float $retrievalScore,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'document_id' => $this->documentId,
            'reference' => $this->reference,
            'title' => $this->title,
            'source_type' => $this->sourceType,
            'source_name' => $this->sourceName,
            'retrieval_score' => $this->retrievalScore,
            'metadata' => $this->metadata,
        ];
    }
}
