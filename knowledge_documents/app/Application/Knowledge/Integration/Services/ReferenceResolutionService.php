<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Integration\Services;

use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\Integration\DTOs\KnowledgeDocumentSummary;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ReferenceResolutionService
{
    /**
     * @param  array{source_name?: string, source_type?: string, translation?: string}  $filters
     */
    public function resolve(string $reference, array $filters = []): ?KnowledgeDocumentSummary
    {
        $record = $this->query($reference, $filters)->first();

        if (! $record instanceof KnowledgeDocumentRecord) {
            return null;
        }

        return KnowledgeDocumentSummary::fromDocument(KnowledgeDocumentData::fromRecord($record));
    }

    /**
     * @param  array{source_name?: string, source_type?: string, translation?: string}  $filters
     * @return Builder<KnowledgeDocumentRecord>
     */
    private function query(string $reference, array $filters): Builder
    {
        $query = KnowledgeDocumentRecord::query()
            ->whereRaw('lower(reference) = lower(?)', [$reference]);

        if (($filters['source_name'] ?? '') !== '') {
            $query->where('source_name', (string) $filters['source_name']);
        }

        if (($filters['source_type'] ?? '') !== '') {
            $query->where('source_type', (string) $filters['source_type']);
        }

        if (($filters['translation'] ?? '') !== '') {
            $this->whereTranslation($query, (string) $filters['translation']);
        }

        return $query
            ->orderByRaw($this->canonicalBibleSourceOrderSql(), [
                SourceType::BibleVerse->value,
                SourceType::BibleChapter->value,
                (string) config('knowledge.reference_resolution.canonical_bible_source_name', 'Douay-Rheims Bible'),
            ])
            ->orderByRaw($this->canonicalBibleTranslationOrderSql(), [
                SourceType::BibleVerse->value,
                SourceType::BibleChapter->value,
                $this->normalizeTranslation((string) config('knowledge.reference_resolution.canonical_bible_translation', 'douay_rheims')),
            ])
            ->orderBy('source_type')
            ->orderBy('source_name')
            ->orderBy('reference');
    }

    /**
     * @param  Builder<KnowledgeDocumentRecord>  $query
     */
    private function whereTranslation(Builder $query, string $translation): void
    {
        $normalized = $this->normalizeTranslation($translation);

        if (DB::getDriverName() === 'pgsql') {
            $query->whereRaw("replace(lower(metadata->>'translation'), '-', '_') = ?", [$normalized]);

            return;
        }

        $query->whereRaw("replace(lower(json_extract(metadata, '$.translation')), '-', '_') = ?", [$normalized]);
    }

    private function canonicalBibleSourceOrderSql(): string
    {
        return 'case when source_type in (?, ?) and source_name = ? then 0 else 1 end';
    }

    private function canonicalBibleTranslationOrderSql(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "case when source_type in (?, ?) and replace(lower(coalesce(metadata->>'translation', '')), '-', '_') = ? then 0 else 1 end";
        }

        return "case when source_type in (?, ?) and replace(lower(coalesce(json_extract(metadata, '$.translation'), '')), '-', '_') = ? then 0 else 1 end";
    }

    private function normalizeTranslation(string $translation): string
    {
        return Str::of($translation)->lower()->replace('-', '_')->replace(' ', '_')->toString();
    }
}
