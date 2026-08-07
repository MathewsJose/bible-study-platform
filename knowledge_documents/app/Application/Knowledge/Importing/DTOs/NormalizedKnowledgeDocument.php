<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\DTOs;

final readonly class NormalizedKnowledgeDocument
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceType,
        public string $sourceName,
        public string $tradition,
        public string $reference,
        public string $title,
        public string $content,
        public string $language,
        public string $checksum,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPersistencePayload(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_name' => $this->sourceName,
            'tradition' => $this->tradition,
            'reference' => $this->reference,
            'title' => $this->title,
            'content' => $this->content,
            'metadata' => array_merge($this->metadata, [
                'language' => $this->language,
                'content_checksum' => $this->checksum,
            ]),
        ];
    }
}
