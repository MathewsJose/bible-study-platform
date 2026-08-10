<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class KnowledgeDocumentDTO
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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            reference: (string) ($data['reference'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            sourceType: (string) ($data['source_type'] ?? ''),
            sourceName: (string) ($data['source_name'] ?? ''),
            tradition: (string) ($data['tradition'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            metadata: (array) ($data['metadata'] ?? []),
            score: isset($data['score']) ? (float) $data['score'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
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
