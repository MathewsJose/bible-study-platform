<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class KnowledgeStatusCommand extends Command
{
    protected $signature = 'knowledge:status';

    protected $description = 'Report knowledge source import status and embedding coverage.';

    public function handle(KnowledgeSourceRegistry $sources): int
    {
        $rows = [];
        $this->line('Registered sources: '.count($sources->all()));
        $this->displayBibleStatus();
        $this->displayCatechismStatus();

        foreach ($sources->all() as $importer) {
            $sourceTypes = $this->documentSourceTypes($importer->identifier());

            $documentCount = KnowledgeDocumentRecord::query()->whereIn('source_type', $sourceTypes)->count();

            $embeddedCount = KnowledgeDocumentRecord::query()
                ->where('embedding_status', EmbeddingStatus::Ready->value)
                ->whereIn('source_type', $sourceTypes)
                ->count();

            $lastImport = ImportManifest::query()
                ->where('source_type', $importer->identifier())
                ->where('status', 'completed')
                ->latest('finished_at')
                ->first();

            $rows[] = [
                $importer->displayName(),
                $importer->identifier(),
                $documentCount,
                $documentCount === 0 ? '0.00%' : number_format(($embeddedCount / $documentCount) * 100, 2).'%',
                $lastImport?->finished_at?->toDateTimeString() ?? 'never',
            ];
        }

        $this->table(['Source', 'Identifier', 'Documents', 'Embedding Coverage', 'Last Import'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function documentSourceTypes(string $sourceIdentifier): array
    {
        return match ($sourceIdentifier) {
            'bible' => [SourceType::BibleVerse->value, SourceType::BibleChapter->value],
            'catechism' => [SourceType::Catechism->value],
            'church_fathers' => [SourceType::ChurchFather->value],
            default => [],
        };
    }

    private function displayBibleStatus(): void
    {
        $verseCount = KnowledgeDocumentRecord::query()->where('source_type', SourceType::BibleVerse->value)->count();
        $chapterCount = KnowledgeDocumentRecord::query()->where('source_type', SourceType::BibleChapter->value)->count();
        $embeddedCount = KnowledgeDocumentRecord::query()
            ->whereIn('source_type', [SourceType::BibleVerse->value, SourceType::BibleChapter->value])
            ->where('embedding_status', EmbeddingStatus::Ready->value)
            ->count();
        $totalBibleDocuments = $verseCount + $chapterCount;
        $lastImport = ImportManifest::query()
            ->where('source_type', 'bible')
            ->latest('finished_at')
            ->first();
        $validationFailures = ImportManifest::query()
            ->where('source_type', 'bible')
            ->where('status', 'failed')
            ->sum('records_failed');

        $this->line('');
        $this->line('Bible Import Status');
        $this->line('Books imported: '.$this->distinctMetadataCount('book', SourceType::BibleVerse->value));
        $this->line('Old Testament count: '.$this->testamentCount('Old Testament'));
        $this->line('New Testament count: '.$this->testamentCount('New Testament'));
        $this->line("Chapter count: {$chapterCount}");
        $this->line("Verse count: {$verseCount}");
        $this->line('Embedding coverage: '.($totalBibleDocuments === 0 ? '0.00%' : number_format(($embeddedCount / $totalBibleDocuments) * 100, 2).'%'));
        $this->line('Last import timestamp: '.($lastImport?->finished_at?->toDateTimeString() ?? 'never'));
        $this->line('Duplicate count: '.$this->duplicateBibleReferenceCount());
        $this->line("Validation failures: {$validationFailures}");

        $translationRows = $this->translationCoverageRows();
        if ($translationRows !== []) {
            $this->table(['Translation', 'Verses', 'Chapters'], $translationRows);
        }
    }

    private function distinctMetadataCount(string $key, string $sourceType): int
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', $sourceType)
            ->selectRaw('count(distinct '.$this->metadataExpression($key).') as aggregate')
            ->value('aggregate') ?: 0;
    }

    private function testamentCount(string $testament): int
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', SourceType::BibleVerse->value)
            ->whereRaw($this->metadataExpression('testament').' = ?', [$testament])
            ->selectRaw('count(distinct '.$this->metadataExpression('book').') as aggregate')
            ->value('aggregate') ?: 0;
    }

    private function duplicateBibleReferenceCount(): int
    {
        return DB::query()
            ->fromSub(
                KnowledgeDocumentRecord::query()
                    ->whereIn('source_type', [SourceType::BibleVerse->value, SourceType::BibleChapter->value])
                    ->select('source_name', 'reference')
                    ->selectRaw('count(*) as duplicate_count')
                    ->groupBy('source_name', 'reference')
                    ->havingRaw('count(*) > 1'),
                'duplicates',
            )
            ->count();
    }

    /**
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function translationCoverageRows(): array
    {
        $translationExpression = $this->metadataExpression('translation');

        return KnowledgeDocumentRecord::query()
            ->whereIn('source_type', [SourceType::BibleVerse->value, SourceType::BibleChapter->value])
            ->selectRaw("coalesce({$translationExpression}, 'unknown') as translation")
            ->selectRaw("sum(case when source_type = ? then 1 else 0 end) as verses", [SourceType::BibleVerse->value])
            ->selectRaw("sum(case when source_type = ? then 1 else 0 end) as chapters", [SourceType::BibleChapter->value])
            ->groupBy('translation')
            ->orderBy('translation')
            ->get()
            ->map(static fn (KnowledgeDocumentRecord $record): array => [
                (string) $record->getAttribute('translation'),
                (int) $record->getAttribute('verses'),
                (int) $record->getAttribute('chapters'),
            ])
            ->all();
    }

    private function metadataExpression(string $key): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "metadata->>'{$key}'";
        }

        return "json_extract(metadata, '$.{$key}')";
    }

    private function displayCatechismStatus(): void
    {
        $paragraphCount = KnowledgeDocumentRecord::query()
            ->where('source_type', SourceType::Catechism->value)
            ->where('reference', 'like', 'CCC %')
            ->count();
        $embeddedCount = KnowledgeDocumentRecord::query()
            ->where('source_type', SourceType::Catechism->value)
            ->where('reference', 'like', 'CCC %')
            ->where('embedding_status', EmbeddingStatus::Ready->value)
            ->count();
        $lastImport = ImportManifest::query()
            ->where('source_type', 'catechism')
            ->latest('finished_at')
            ->first();

        $this->line('');
        $this->line('Catechism Import Status');
        $this->line("Total CCC paragraphs: {$paragraphCount}");
        $this->line('Parts: '.$this->distinctMetadataCount('part', SourceType::Catechism->value));
        $this->line('Sections: '.$this->distinctMetadataCount('section', SourceType::Catechism->value));
        $this->line('Articles: '.$this->distinctMetadataCount('article', SourceType::Catechism->value));
        $this->line('Cross references: '.$this->metadataArrayItemCount('internal_references', SourceType::Catechism->value));
        $this->line('Scripture references: '.$this->metadataArrayItemCount('scripture_references', SourceType::Catechism->value));
        $this->line('Embedding coverage: '.($paragraphCount === 0 ? '0.00%' : number_format(($embeddedCount / $paragraphCount) * 100, 2).'%'));
        $this->line('Last import time: '.($lastImport?->finished_at?->toDateTimeString() ?? 'never'));
    }

    private function metadataArrayItemCount(string $key, string $sourceType): int
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', $sourceType)
            ->get()
            ->sum(static function (KnowledgeDocumentRecord $record) use ($key): int {
                $metadata = $record->metadata;
                $items = is_array($metadata) && is_array($metadata[$key] ?? null) ? $metadata[$key] : [];

                return count($items);
            });
    }
}
