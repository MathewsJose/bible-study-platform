<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Replay\Services;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class DocumentFingerprintService
{
    public function __construct(private StableJsonHasher $hasher) {}

    /** @return array<string, mixed> */
    public function fingerprint(KnowledgeDocumentRecord $document): array
    {
        $metadata = is_array($document->metadata) ? $document->metadata : [];

        $payload = [
            'id' => $document->id,
            'source_type' => $document->source_type,
            'source_name' => $document->source_name,
            'tradition' => $document->tradition,
            'reference' => $document->reference,
            'title' => $document->title,
            'content_hash' => hash('sha256', trim(preg_replace('/\s+/u', ' ', $document->content) ?? $document->content)),
            'metadata_checksum' => $metadata['checksum'] ?? null,
            'embedding_provider' => $document->embedding_provider,
            'embedding_model' => $document->embedding_model,
            'embedding_dimensions' => $document->embedding_dimensions,
        ];

        return [
            'hash' => $this->hasher->hash($payload),
            'payload' => $payload,
        ];
    }
}
