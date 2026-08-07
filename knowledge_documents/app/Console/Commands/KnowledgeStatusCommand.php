<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;

final class KnowledgeStatusCommand extends Command
{
    protected $signature = 'knowledge:status';

    protected $description = 'Report knowledge source import status and embedding coverage.';

    public function handle(KnowledgeSourceRegistry $sources): int
    {
        $rows = [];
        $this->line('Registered sources: '.count($sources->all()));

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
}
