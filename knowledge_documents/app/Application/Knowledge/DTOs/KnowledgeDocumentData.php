<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class KnowledgeDocumentData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $sourceType,
        public string $sourceName,
        public string $tradition,
        public string $reference,
        public string $title,
        public string $content,
        public array $metadata,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromRecord(KnowledgeDocumentRecord $record): self
    {
        return new self(
            id: $record->id,
            sourceType: $record->source_type,
            sourceName: $record->source_name,
            tradition: $record->tradition,
            reference: $record->reference,
            title: $record->title,
            content: $record->content,
            metadata: $record->metadata,
            createdAt: (string) $record->created_at?->toJSON(),
            updatedAt: (string) $record->updated_at?->toJSON(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->sourceType,
            'source_name' => $this->sourceName,
            'tradition' => $this->tradition,
            'reference' => $this->reference,
            'title' => $this->title,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
