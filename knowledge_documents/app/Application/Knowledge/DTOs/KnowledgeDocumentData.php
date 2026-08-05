<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

use App\Domain\Knowledge\Enums\EmbeddingStatus;
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
        public string $embeddingStatus,
        public ?string $embeddingModel = null,
        public ?string $embeddingProvider = null,
        public ?int $embeddingDimensions = null,
        public ?string $embeddedAt = null,
        public ?string $embeddingError = null,
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
            embeddingStatus: ($record->embedding_status ?? EmbeddingStatus::Pending)->value,
            embeddingModel: $record->embedding_model,
            embeddingProvider: $record->embedding_provider,
            embeddingDimensions: $record->embedding_dimensions,
            embeddedAt: $record->embedded_at?->toJSON(),
            embeddingError: $record->embedding_error,
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
            'embedding_status' => $this->embeddingStatus,
            'embedding_model' => $this->embeddingModel,
            'embedding_provider' => $this->embeddingProvider,
            'embedding_dimensions' => $this->embeddingDimensions,
            'embedded_at' => $this->embeddedAt,
            'embedding_error' => $this->embeddingError,
        ];
    }
}
