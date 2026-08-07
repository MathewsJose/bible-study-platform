<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use App\Application\Knowledge\Importing\Services\ImportPipeline;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

final class KnowledgeImportCommand extends Command
{
    protected $signature = 'knowledge:import
                            {source=all : Source identifier to import, or all}
                            {--skip-unchanged : Skip files whose checksum was already imported}
                            {--force : Import even when the file checksum was already imported}
                            {--no-embeddings : Do not queue embedding generation after persistence}
                            {--source-url= : The source URL of the data}
                            {--license= : The license of the data}
                            {--license-url= : The URL to the license text}
                            {--rights-notes= : Additional rights or copyright notes}
                            {--language=en : The language of the documents}';

    /** @var list<string> */
    protected $aliases = ['knowledge'];

    protected $description = 'Import knowledge documents through the source registry and import pipeline.';

    public function __construct(
        private readonly KnowledgeSourceRegistry $sources,
        private readonly ImportPipeline $pipeline,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $directories = config('knowledge.import.directories', []);
        $files = $this->collectFiles($directories);

        $metadata = array_filter([
            'source_url' => $this->option('source-url'),
            'license' => $this->option('license'),
            'license_url' => $this->option('license-url'),
            'rights_notes' => $this->option('rights-notes'),
            'language' => $this->option('language'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $embeddingsQueued = 0;
        $filesScanned = 0;
        $filesImported = 0;
        $filesSkipped = 0;
        $filesFailed = 0;

        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $filesScanned++;

            $importer = $this->resolveImporter($source, $path);
            if (! $importer instanceof KnowledgeImporterInterface) {
                continue;
            }

            $result = $this->pipeline->import($importer, $path, $metadata, [
                'skip_unchanged' => true,
                'force' => (bool) $this->option('force'),
                'queue_embeddings' => ! (bool) $this->option('no-embeddings'),
            ]);

            $imported += $result->created;
            $updated += $result->updated;
            $skipped += $result->skipped;
            $failed += $result->failed;
            $embeddingsQueued += $result->embeddingsQueued;

            if ($result->failed === 0 && $result->imported() > 0) {
                $filesImported++;
            } elseif ($result->failed > 0) {
                $filesFailed++;
                foreach ($result->errors as $error) {
                    $this->error("Failed to import {$path}: {$error}");
                }
            } else {
                $filesSkipped++;
            }
        }

        $this->line("files scanned: {$filesScanned}");
        $this->line("files imported: {$filesImported}");
        $this->line("files skipped: {$filesSkipped}");
        $this->line("files failed: {$filesFailed}");
        $this->line("imported: {$imported}");
        $this->line("updated: {$updated}");
        $this->line("skipped: {$skipped}");
        $this->line("failed: {$failed}");
        $this->line("embeddings queued: {$embeddingsQueued}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<string> $directories */
    private function collectFiles(array $directories): Collection
    {
        $files = new Collection();

        foreach ($directories as $directory) {
            $resolved = $this->resolveDirectory($directory);
            if ($resolved === null || ! is_dir($resolved)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($resolved)->name(['*.json', '*.txt', '*.md'])->sortByName();

            foreach ($finder as $file) {
                $files->push($file);
            }
        }

        return $files;
    }

    private function resolveDirectory(string $directory): ?string
    {
        $directory = trim($directory);
        if ($directory === '') {
            return null;
        }

        if (str_starts_with($directory, DIRECTORY_SEPARATOR) || (strlen($directory) >= 2 && ctype_alpha($directory[0]) && $directory[1] === ':')) {
            return $directory;
        }

        return base_path($directory);
    }

    private function resolveImporter(string $source, string $path): ?KnowledgeImporterInterface
    {
        if ($source !== 'all') {
            $importer = $this->sources->resolve($source);

            return $importer->supports($path) ? $importer : null;
        }

        return $this->sources->detect($path);
    }
}
