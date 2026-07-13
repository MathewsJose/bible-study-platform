<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class KnowledgeAuditCommand extends Command
{
    protected $signature = 'knowledge:audit
                            {--source-type= : Filter by source type}
                            {--source-name= : Filter by source name}';

    protected $description = 'Provide a report about imported knowledge documents and data quality.';

    public function handle(): int
    {
        $query = KnowledgeDocumentRecord::query();

        if ($sourceType = $this->option('source-type')) {
            $query->where('source_type', $sourceType);
        }

        if ($sourceName = $this->option('source-name')) {
            $query->where('source_name', $sourceName);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No documents found matching filters.');
            return self::SUCCESS;
        }

        $this->info("Corpus Audit Report");
        $this->info("-------------------");
        $this->line("Total Documents: {$total}");
        $this->newLine();

        $this->info("Counts by Source Type:");
        $this->displayCounts((clone $query), 'source_type');
        $this->newLine();

        $this->info("Counts by Source Name:");
        $this->displayCounts((clone $query), 'source_name');
        $this->newLine();

        $this->info("Counts by Tradition:");
        $this->displayCounts((clone $query), 'tradition');
        $this->newLine();

        $this->info("Data Quality:");
        
        $missingContent = (clone $query)->where(fn($q) => $q->whereNull('content')->orWhere('content', ''))->count();
        $this->reportQuality("Missing Content", $missingContent, $total);

        // Metadata checks (using JSON operators if possible, but safely)
        $missingSourceUrl = (clone $query)->where(function(Builder $q) {
            $q->whereNull('metadata->source_url');
        })->count();
        $this->reportQuality("Missing source_url", $missingSourceUrl, $total);

        $missingLicense = (clone $query)->where(function(Builder $q) {
            $q->whereNull('metadata->license');
        })->count();
        $this->reportQuality("Missing license", $missingLicense, $total);

        $this->newLine();
        $this->info("Embedding Status:");
        
        $pending = (clone $query)->where('embedding_status', EmbeddingStatus::Pending)->count();
        $this->reportQuality("Pending Embeddings", $pending, $total, 'yellow');

        $failed = (clone $query)->where('embedding_status', EmbeddingStatus::Failed)->count();
        $this->reportQuality("Failed Embeddings", $failed, $total, 'red');

        $ready = (clone $query)->where('embedding_status', EmbeddingStatus::Ready)->count();
        $this->reportQuality("Ready Embeddings", $ready, $total, 'green');

        return self::SUCCESS;
    }

    private function displayCounts(Builder $query, string $column): void
    {
        $counts = $query->select($column, DB::raw('count(*) as aggregate'))
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->get();

        foreach ($counts as $row) {
            $this->line("- {$row->{$column}}: {$row->aggregate}");
        }
    }

    private function reportQuality(string $label, int $count, int $total, string $color = 'default'): void
    {
        $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
        $formatted = "{$label}: {$count} ({$percentage}%)";
        
        if ($color === 'red' && $count > 0) {
            $this->error($formatted);
        } elseif ($color === 'yellow' && $count > 0) {
            $this->warn($formatted);
        } elseif ($color === 'green') {
            $this->info($formatted);
        } else {
            $this->line($formatted);
        }
    }
}
