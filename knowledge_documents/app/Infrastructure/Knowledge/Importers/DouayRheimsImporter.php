<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Domain\Knowledge\ValueObjects\SourceMetadata;

final readonly class DouayRheimsImporter extends AbstractBibleImporter
{
    public const SOURCE_NAME = 'Douay-Rheims Bible';

    public function sourceName(): string
    {
        return self::SOURCE_NAME;
    }

    protected function shouldImportChapters(): bool
    {
        return true;
    }

    protected function documentPayload(array $validatedPayload, array $verse, ?SourceMetadata $sourceMetadata = null): array
    {
        $payload = parent::documentPayload($validatedPayload, $verse, $sourceMetadata);

        $payload['metadata'] = array_merge($payload['metadata'], [
            'canon' => 'catholic',
            'translation' => 'douay_rheims',
            'language' => $payload['metadata']['language'] ?? 'en',
        ]);

        return $payload;
    }

    protected function chapterDocumentPayload(array $validatedPayload, ?SourceMetadata $sourceMetadata = null): array
    {
        $payload = parent::chapterDocumentPayload($validatedPayload, $sourceMetadata);

        $payload['metadata'] = array_merge($payload['metadata'], [
            'canon' => 'catholic',
            'translation' => 'douay_rheims',
            'language' => $payload['metadata']['language'] ?? 'en',
        ]);

        return $payload;
    }
}
