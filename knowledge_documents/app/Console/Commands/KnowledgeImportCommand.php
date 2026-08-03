<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Infrastructure\Knowledge\Importers\BibleImporter;
use App\Infrastructure\Knowledge\Importers\DouayRheimsImporter;
use App\Infrastructure\Knowledge\Importers\CatechismImporter;
use App\Infrastructure\Knowledge\Importers\ModernCatechismImporter;
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
    protected $signature = 'knowledge:import
                            {--source-url= : The source URL of the data}
                            {--license= : The license of the data}
                            {--license-url= : The URL to the license text}
                            {--rights-notes= : Additional rights or copyright notes}
                            {--language=en : The language of the documents}';

    protected $description = 'Scan configured import directories, detect files, and import supported documents.';

    /** @var array<string, callable> */
    private array $importers;

    public function __construct(
        private readonly BibleImporter $bibleImporter,
        private readonly DouayRheimsImporter $douayRheimsImporter,
        private readonly CatechismImporter $catechismImporter,
        private readonly ModernCatechismImporter $modernCatechismImporter,
        private readonly ChurchFatherImporter $churchFatherImporter,
    ) {
        parent::__construct();

        $this->importers = [
            'bible' => fn (string $path, array $metadata = []): ImportResult => $this->bibleImporter->importFile($path, $metadata),
            'douay_rheims' => fn (string $path, array $metadata = []): ImportResult => $this->douayRheimsImporter->importFile($path, $metadata),
            'catechism' => fn (string $path, array $metadata = []): ImportResult => $this->catechismImporter->importFile($path, $metadata),
            'ccc' => fn (string $path, array $metadata = []): ImportResult => $this->modernCatechismImporter->importFile($path, $metadata),
            'church_father' => fn (string $path, array $metadata = []): ImportResult => $this->churchFatherImporter->importFile($path, $metadata),
        ];
    }

    public function handle(): int
    {
        $directories = config('knowledge.import.directories', []);
        $files = $this->collectFiles($directories);

        $metadata = array_filter([
            'source_url' => $this->option('source-url'),
            'license' => $this->option('license'),
            'license_url' => $this->option('license-url'),
            'rights_notes' => $this->option('rights-notes'),
            'language' => $this->option('language'),
        ]);

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
                $result = $this->importers[$fileType]($path, $metadata);

                $imported += $result->created;
                $skipped += $result->skipped;
                $failed += $result->failures;
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
            $lowerPath = strtolower($path);
            if (str_contains($lowerPath, 'douay-rheims')) {
                return 'douay_rheims';
            }

            if (str_contains($lowerPath, 'ccc') || str_contains($lowerPath, 'modern-catechism')) {
                return 'ccc';
            }

            if (str_contains($lowerPath, 'catechism')) {
                return 'catechism';
            }

            if (str_contains($lowerPath, 'church-father') || str_contains($lowerPath, 'church_father')) {
                return 'church_father';
            }

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

        return ImportManifest::query()->where('checksum', $hash)->where('status', 'completed')->exists();
    }

}
