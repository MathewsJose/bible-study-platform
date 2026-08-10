<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Replay\Services;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;

final readonly class CorpusFingerprintService
{
    public function __construct(
        private DocumentFingerprintService $documents,
        private StableJsonHasher $hasher,
    ) {}

    /** @return array<string, mixed> */
    public function fingerprint(): array
    {
        $documents = KnowledgeDocumentRecord::query()
            ->select(['id', 'source_type', 'source_name', 'tradition', 'reference', 'title', 'content', 'metadata', 'embedding_provider', 'embedding_model', 'embedding_dimensions'])
            ->orderBy('id')
            ->get();

        $documentHashes = $documents
            ->map(fn (KnowledgeDocumentRecord $document): array => [
                'id' => $document->id,
                'reference' => $document->reference,
                'hash' => $this->documents->fingerprint($document)['hash'],
            ])
            ->values()
            ->all();

        $embeddingModels = $documents
            ->map(static fn (KnowledgeDocumentRecord $document): string => ($document->embedding_provider ?? 'none').':'.($document->embedding_model ?? 'none').':'.($document->embedding_dimensions ?? 'none'))
            ->unique()
            ->values()
            ->all();

        $payload = [
            'document_count' => $documents->count(),
            'document_hashes' => $documentHashes,
            'embedding_models' => $embeddingModels,
            'retrieval_profiles' => config('retrieval.profiles', []),
        ];

        return [
            'hash' => $this->hasher->hash($payload),
            'document_count' => $documents->count(),
            'embedding_models' => $embeddingModels,
        ];
    }
}
