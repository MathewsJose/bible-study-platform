<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Services;

use App\Application\Knowledge\Retrieval\DTOs\RetrievalCandidate;

final readonly class MetadataFilterService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function normalize(array $filters): array
    {
        return array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  list<RetrievalCandidate>  $candidates
     * @param  array<string, mixed>  $filters
     * @return list<RetrievalCandidate>
     */
    public function apply(array $candidates, array $filters): array
    {
        $filters = $this->normalize($filters);

        if ($filters === []) {
            return $candidates;
        }

        return array_values(array_filter(
            $candidates,
            fn (RetrievalCandidate $candidate): bool => $this->matches($candidate, $filters),
        ));
    }

    /** @param  array<string, mixed>  $filters */
    private function matches(RetrievalCandidate $candidate, array $filters): bool
    {
        $metadata = $candidate->document->metadata;

        foreach ($filters as $key => $value) {
            if ($key === 'source_types') {
                if (! in_array($candidate->document->sourceType, (array) $value, true)) {
                    return false;
                }

                continue;
            }

            if ($key === 'source_type' && $candidate->document->sourceType !== $value) {
                return false;
            }

            if ($key === 'source_name' && $candidate->document->sourceName !== $value) {
                return false;
            }

            if ($key === 'tradition' && $candidate->document->tradition !== $value) {
                return false;
            }

            if (in_array($key, ['author', 'book', 'chapter', 'language', 'translation', 'century', 'theological_topic', 'topic'], true)) {
                $metadataKey = $key === 'theological_topic' ? 'topics' : $key;
                $metadataValue = $metadata[$metadataKey] ?? null;

                if (is_array($metadataValue) && ! in_array($value, $metadataValue, true)) {
                    return false;
                }

                if (! is_array($metadataValue) && (string) $metadataValue !== (string) $value) {
                    return false;
                }
            }
        }

        return true;
    }
}
