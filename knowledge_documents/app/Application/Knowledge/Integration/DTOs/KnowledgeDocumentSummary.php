<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Integration\DTOs;

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;

final readonly class KnowledgeDocumentSummary
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $reference,
        public string $title,
        public string $sourceType,
        public string $sourceName,
        public string $tradition,
        public string $content,
        public array $metadata = [],
        public ?float $score = null,
    ) {}

    public static function fromDocument(KnowledgeDocumentData $document, ?float $score = null): self
    {
        return new self(
            id: $document->id,
            reference: $document->reference,
            title: $document->title,
            sourceType: $document->sourceType,
            sourceName: $document->sourceName,
            tradition: $document->tradition,
            content: $document->content,
            metadata: $document->metadata,
            score: $score,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'source_type' => $this->sourceType,
            'source_name' => $this->sourceName,
            'tradition' => $this->tradition,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'score' => $this->score,
        ];
    }
}
