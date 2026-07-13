<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Infrastructure\Knowledge\Importers\BibleImporter;
use App\Infrastructure\Knowledge\Importers\CatechismImporter;
use App\Infrastructure\Knowledge\Importers\ChurchFatherImporter;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

final class KnowledgeImportCommand extends Command
{
    protected $signature = 'knowledge:import';

    protected $description = 'Scan configured import directories, detect files, and import supported documents.';

    /** @var array<string, callable> */
    private array $importers;

    public function __construct(
        private readonly BibleImporter $bibleImporter,
        private readonly CatechismImporter $catechismImporter,
        private readonly ChurchFatherImporter $churchFatherImporter,
    ) {
        parent::__construct();

        $this->importers = [
            'bible' => fn (string $path): ImportResult => $this->bibleImporter->importFile($path),
            'catechism' => fn (string $path): ImportResult => $this->importTextFile($path, $this->catechismImporter),
            'church_father' => fn (string $path): ImportResult => $this->importTextFile($path, $this->churchFatherImporter),
        ];
    }

    public function handle(): int
    {
        $directories = config('knowledge.import.directories', []);
        $files = $this->collectFiles($directories);

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $file) {
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $fileType = $this->detectFileType($path);
            if ($fileType === null) {
                continue;
            }

            if ($this->isAlreadyImported($path)) {
                $skipped++;
                continue;
            }

            try {
                $result = $this->importers[$fileType]($path);
                $imported += $result->imported;
                $skipped += $result->skippedDuplicates;
                $failed += $result->failures;
                $this->persistManifest($path, $fileType, $result);
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("Failed to import {$path}: {$exception->getMessage()}");
            }
        }

        $this->line("imported: {$imported}");
        $this->line("skipped: {$skipped}");
        $this->line("failed: {$failed}");

        return self::SUCCESS;
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
            $finder->files()->in($resolved)->name(['*.json', '*.txt', '*.md']);

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

    private function detectFileType(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'json') {
            return 'bible';
        }

        if (in_array($extension, ['txt', 'md'], true)) {
            $basename = strtolower(basename($path));
            if (str_contains($basename, 'catechism')) {
                return 'catechism';
            }

            if (str_contains($basename, 'church') || str_contains($basename, 'father')) {
                return 'church_father';
            }

            return 'catechism';
        }

        return null;
    }

    private function isAlreadyImported(string $path): bool
    {
        $hash = hash_file('sha256', $path);

        return ImportManifest::query()->where('file_hash', $hash)->exists();
    }

    private function persistManifest(string $path, string $fileType, ImportResult $result): void
    {
        ImportManifest::query()->create([
            'file_path' => $path,
            'file_hash' => hash_file('sha256', $path),
            'file_type' => $fileType,
            'source_name' => $this->sourceNameFor($path, $fileType),
            'total_records' => $result->imported + $result->skippedDuplicates + $result->failures,
            'imported_records' => $result->imported,
            'skipped_records' => $result->skippedDuplicates,
            'failed_records' => $result->failures,
            'imported_at' => now(),
        ]);
    }

    private function sourceNameFor(string $path, string $fileType): string
    {
        if ($fileType === 'bible') {
            return 'Bible';
        }

        return basename($path);
    }

    private function importTextFile(string $path, object $importer): ImportResult
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return new ImportResult(0, 0, 1);
        }

        $segments = preg_split('/\n\s*\n/', trim($content)) ?: [];
        $records = [];
        foreach ($segments as $index => $segment) {
            $records[] = [
                'source_name' => basename($path),
                'reference' => basename($path).'#'.($index + 1),
                'title' => basename($path).'#'.($index + 1),
                'content' => trim($segment),
                'tradition' => 'catholic',
                'metadata' => ['source_file' => basename($path), 'segment' => $index + 1],
            ];
        }

        $importer->import($records);

        return new ImportResult(count($records), 0, 0);
    }
}
