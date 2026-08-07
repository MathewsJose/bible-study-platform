<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use Illuminate\Console\Command;

final class KnowledgeSourcesCommand extends Command
{
    protected $signature = 'knowledge:sources';

    protected $description = 'List registered knowledge source importers.';

    public function handle(KnowledgeSourceRegistry $sources): int
    {
        $rows = [];

        foreach ($sources->all() as $importer) {
            $rows[] = [
                $importer->displayName(),
                $importer->identifier(),
                $importer->version(),
                implode(', ', $importer->supportedLanguages()),
                $importer->licensing()['license'] ?? '',
            ];
        }

        $this->table(['Source', 'Identifier', 'Version', 'Languages', 'License'], $rows);

        return self::SUCCESS;
    }
}
