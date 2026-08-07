<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Graph\Services\KnowledgeGraphBuilder;
use Illuminate\Console\Command;

final class GraphRebuildCommand extends Command
{
    protected $signature = 'graph:rebuild
                            {--document-id= : Rebuild relationships for one document}
                            {--source-type= : Rebuild relationships for one source type}';

    protected $description = 'Rebuild explicit knowledge graph relationships from imported document metadata.';

    public function handle(KnowledgeGraphBuilder $builder): int
    {
        $result = $builder->rebuild($this->filters());

        $this->line('Knowledge graph rebuild complete.');
        $this->line("documents processed: {$result->documentsProcessed}");
        $this->line("relationships created: {$result->relationshipsCreated}");
        $this->line("relationships updated: {$result->relationshipsUpdated}");
        $this->line("relationships removed: {$result->relationshipsRemoved}");
        $this->line("orphaned references: {$result->orphanedReferenceCount}");
        $this->line("duration seconds: {$result->durationSeconds}");

        return self::SUCCESS;
    }

    /** @return array{document_id?: string, source_type?: string} */
    private function filters(): array
    {
        return array_filter([
            'document_id' => $this->option('document-id'),
            'source_type' => $this->option('source-type'),
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');
    }
}
