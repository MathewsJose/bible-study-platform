<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;

final class KnowledgeDuplicatesCommand extends Command
{
    protected $signature = 'knowledge:duplicates
                            {--source-type= : Filter by source type}
                            {--source-name= : Filter by source name}
                            {--format=table : Output format: table or json}';

    protected $description = 'Report duplicate plain references across and within knowledge sources.';

    public function handle(): int
    {
        $sourceType = $this->option('source-type') ? (string) $this->option('source-type') : null;
        $sourceName = $this->option('source-name') ? (string) $this->option('source-name') : null;

        $withinSource = $this->withinSourceDuplicates($sourceType, $sourceName);
        $acrossSources = $this->acrossSourceDuplicates($sourceType, $sourceName);
        $payload = [
            'summary' => [
                'within_source_duplicates' => count($withinSource),
                'across_source_duplicates' => count($acrossSources),
                'accidental_duplicates_detected' => $withinSource !== [],
                'legitimate_cross_source_duplicates_detected' => $acrossSources !== [],
            ],
            'within_source_duplicates' => $withinSource,
            'across_source_duplicates' => $acrossSources,
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->line('Duplicate Reference Diagnostics');
        $this->line('Accidental duplicates within same source: '.count($withinSource));
        $this->line('Legitimate duplicates across source names: '.count($acrossSources));

        if ($withinSource !== []) {
            $this->table(['Reference', 'Source Type', 'Source Name', 'Count'], array_map(
                static fn (array $row): array => [$row['reference'], $row['source_type'], $row['source_name'], $row['count']],
                $withinSource,
            ));
        }

        if ($acrossSources !== []) {
            $this->table(['Reference', 'Source Type', 'Source Names', 'Count'], array_map(
                static fn (array $row): array => [$row['reference'], $row['source_type'], implode(', ', $row['source_names']), $row['count']],
                $acrossSources,
            ));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{reference: string, source_type: string, source_name: string, count: int}>
     */
    private function withinSourceDuplicates(?string $sourceType, ?string $sourceName): array
    {
        return KnowledgeDocumentRecord::query()
            ->select('reference', 'source_type', 'source_name')
            ->selectRaw('count(*) as duplicate_count')
            ->when($sourceType !== null, fn ($query) => $query->where('source_type', $sourceType))
            ->when($sourceName !== null, fn ($query) => $query->where('source_name', $sourceName))
            ->groupBy('reference', 'source_type', 'source_name')
            ->havingRaw('count(*) > 1')
            ->orderBy('reference')
            ->get()
            ->map(static fn (KnowledgeDocumentRecord $record): array => [
                'reference' => (string) $record->reference,
                'source_type' => (string) $record->source_type,
                'source_name' => (string) $record->source_name,
                'count' => (int) $record->getAttribute('duplicate_count'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{reference: string, source_type: string, source_names: list<string>, count: int}>
     */
    private function acrossSourceDuplicates(?string $sourceType, ?string $sourceName): array
    {
        $records = KnowledgeDocumentRecord::query()
            ->select('reference', 'source_type', 'source_name')
            ->when($sourceType !== null, fn ($query) => $query->where('source_type', $sourceType))
            ->when($sourceName !== null, fn ($query) => $query->where('source_name', $sourceName))
            ->orderBy('reference')
            ->get(['reference', 'source_type', 'source_name']);

        return $records
            ->groupBy(static fn (KnowledgeDocumentRecord $record): string => $record->source_type.'|'.$record->reference)
            ->map(static function ($group): ?array {
                $sourceNames = $group
                    ->pluck('source_name')
                    ->map(static fn (mixed $value): string => (string) $value)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                if (count($sourceNames) <= 1) {
                    return null;
                }

                $first = $group->first();

                return [
                    'reference' => (string) $first->reference,
                    'source_type' => (string) $first->source_type,
                    'source_names' => $sourceNames,
                    'count' => $group->count(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
