<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Infrastructure\Knowledge\Importers\BibleImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

final class BibleImportCommand extends Command
{
    protected $signature = 'bible:import {path : Path to a Bible chapter JSON file}';

    protected $description = 'Import Bible verses from a JSON chapter file into knowledge documents.';

    public function handle(BibleImporter $importer): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));

        if (! is_file($path)) {
            $this->error("Bible import file not found: {$path}");
            $this->displayResult(new ImportResult(0, 0, 1));

            return self::FAILURE;
        }

        try {
            $result = $importer->importFile($path);
        } catch (ValidationException $exception) {
            $this->error('Bible import validation failed.');

            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->line($message);
                }
            }

            $this->displayResult(new ImportResult(0, 0, 1));

            return self::FAILURE;
        } catch (JsonException $exception) {
            $this->error('Bible import JSON is invalid: '.$exception->getMessage());
            $this->displayResult(new ImportResult(0, 0, 1));

            return self::FAILURE;
        }

        $this->displayResult($result);

        return $result->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function displayResult(ImportResult $result): void
    {
        $this->line("documents imported: {$result->imported}");
        $this->line("skipped duplicates: {$result->skippedDuplicates}");
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
