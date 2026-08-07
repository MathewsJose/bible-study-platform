<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Graph\Services\KnowledgeGraphDiagnosticsService;
use Illuminate\Console\Command;

final class GraphVerifyCommand extends Command
{
    protected $signature = 'graph:verify';

    protected $description = 'Verify knowledge graph integrity and display diagnostics.';

    public function handle(KnowledgeGraphDiagnosticsService $diagnostics): int
    {
        $report = $diagnostics->diagnostics();

        $this->line('Knowledge Graph Diagnostics');
        $this->line("Total graph nodes: {$report->totalNodes}");
        $this->line("Total graph edges: {$report->totalEdges}");
        $this->line("Disconnected nodes: {$report->disconnectedNodes}");
        $this->line("Duplicate relationships: {$report->duplicateRelationships}");
        $this->line("Broken references: {$report->brokenRelationships}");
        $this->line("Average degree: {$report->averageDegree}");
        $this->line("Graph density: {$report->density}");

        $rows = [];
        foreach ($report->relationshipCounts as $type => $count) {
            $rows[] = [$type, $count];
        }

        $this->table(['Relationship Type', 'Count'], $rows);

        return $report->duplicateRelationships === 0 && $report->brokenRelationships === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
