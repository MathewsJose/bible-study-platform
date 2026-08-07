<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\DTOs;

final readonly class RawKnowledgeDocument
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceIdentifier,
        public string $path,
        public string $checksum,
        public string $contents,
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonPayload(): array
    {
        $payload = json_decode($this->contents, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }
}
