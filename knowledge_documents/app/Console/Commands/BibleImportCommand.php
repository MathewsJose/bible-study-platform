<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Application\Knowledge\Importing\Services\ImportPipeline;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use Illuminate\Console\Command;

final class BibleImportCommand extends Command
{
    protected $signature = 'bible:import 
                            {path : Path to a Bible chapter JSON file}
                            {--source-url= : The source URL of the Bible data}
                            {--license= : The license of the Bible data}
                            {--license-url= : The URL to the license text}
                            {--rights-notes= : Additional rights or copyright notes}
                            {--language=en : The language of the documents}';

    protected $description = 'Import Bible verses from a JSON chapter file into knowledge documents.';

    public function handle(KnowledgeSourceRegistry $sources, ImportPipeline $pipeline): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));

        if (! is_file($path)) {
            $this->error("Bible import file not found: {$path}");
            $this->displayResult(new ImportResult(failures: 1));

            return self::FAILURE;
        }

        $metadata = array_filter([
            'source_url' => $this->option('source-url'),
            'license' => $this->option('license'),
            'license_url' => $this->option('license-url'),
            'rights_notes' => $this->option('rights-notes'),
            'language' => $this->option('language'),
        ]);

        $result = $pipeline->import($sources->resolve('bible'), $path, $metadata, [
            'force' => true,
            'skip_unchanged' => false,
        ]);

        if ($result->failed > 0) {
            $this->error(str_contains(implode(' ', $result->errors), 'JSON is invalid')
                ? implode(' ', $result->errors)
                : 'Bible import validation failed.');

            foreach ($result->errors as $error) {
                $this->line($error);
            }

            $this->displayResult(new ImportResult(failures: $result->failed));

            return self::FAILURE;
        }

        $this->displayResult(new ImportResult(
            created: $result->created,
            updated: $result->updated,
            skipped: $result->skipped,
            failures: $result->failed,
        ));

        return self::SUCCESS;
    }

    private function displayResult(ImportResult $result): void
    {
        $this->line("documents imported: {$result->created}");
        $this->line("skipped duplicates: {$result->skipped}");
        $this->line("failures: {$result->failures}");
    }

    private function resolvePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Z]:[\\\\\/]/i', $path) === 1;
    }
}
