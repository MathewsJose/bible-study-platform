<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Graph\Services\KnowledgeGraphBuilder;
use Illuminate\Console\Command;

final class GraphUpdateCommand extends Command
{
    protected $signature = 'graph:update
                            {--document-id= : Update relationships for one document}
                            {--source-type= : Update relationships for one source type}';

    protected $description = 'Incrementally update explicit knowledge graph relationships.';

    public function handle(KnowledgeGraphBuilder $builder): int
    {
        $result = $builder->rebuild($this->filters());

        $this->line('Knowledge graph update complete.');
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
