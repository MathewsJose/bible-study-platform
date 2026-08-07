<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

final class KnowledgeVerifyCommand extends Command
{
    protected $signature = 'knowledge:verify {source=all : Source identifier to verify, or all}';

    protected $description = 'Validate configured knowledge import files without persisting documents.';

    public function handle(KnowledgeSourceRegistry $sources): int
    {
        $source = (string) $this->argument('source');
        $verified = 0;
        $failed = 0;
        $unsupported = 0;

        foreach ($this->collectFiles((array) config('knowledge.import.directories', [])) as $file) {
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $importer = $this->resolveImporter($sources, $source, $path);
            if (! $importer instanceof KnowledgeImporterInterface) {
                $unsupported++;
                continue;
            }

            $raw = $importer->fetch($path);
            $validation = $importer->validate($raw);

            if ($validation->valid) {
                $verified++;
                continue;
            }

            $failed++;
            foreach ($validation->errors as $error) {
                $this->error("{$path}: {$error}");
            }
        }

        $this->line("verified: {$verified}");
        $this->line("unsupported: {$unsupported}");
        $this->line("failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $directories
     * @return Collection<int, SplFileInfo>
     */
    private function collectFiles(array $directories): Collection
    {
        $files = new Collection();

        foreach ($directories as $directory) {
            $resolved = $this->resolveDirectory((string) $directory);
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

        if (str_starts_with($directory, DIRECTORY_SEPARATOR) || preg_match('/^[A-Z]:[\\\\\/]/i', $directory) === 1) {
            return $directory;
        }

        return base_path($directory);
    }

    private function resolveImporter(KnowledgeSourceRegistry $sources, string $source, string $path): ?KnowledgeImporterInterface
    {
        if ($source !== 'all') {
            $importer = $sources->resolve($source);

            return $importer->supports($path) ? $importer : null;
        }

        return $sources->detect($path);
    }
}
