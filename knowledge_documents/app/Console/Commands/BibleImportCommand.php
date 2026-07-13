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
    protected $signature = 'bible:import 
                            {path : Path to a Bible chapter JSON file}
                            {--source-url= : The source URL of the Bible data}
                            {--license= : The license of the Bible data}
                            {--license-url= : The URL to the license text}
                            {--rights-notes= : Additional rights or copyright notes}
                            {--language=en : The language of the documents}';

    protected $description = 'Import Bible verses from a JSON chapter file into knowledge documents.';

    public function handle(BibleImporter $importer): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));

        if (! is_file($path)) {
            $this->error("Bible import file not found: {$path}");
            $this->displayResult(new ImportResult(failures: 1));

            return self::FAILURE;
        }

        try {
            $metadata = array_filter([
                'source_url' => $this->option('source-url'),
                'license' => $this->option('license'),
                'license_url' => $this->option('license-url'),
                'rights_notes' => $this->option('rights-notes'),
                'language' => $this->option('language'),
            ]);

            $result = $importer->importFile($path, $metadata);
        } catch (ValidationException $exception) {
            $this->error('Bible import validation failed.');

            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->line($message);
                }
            }

            $this->displayResult(new ImportResult(failures: 1));

            return self::FAILURE;
        } catch (JsonException $exception) {
            $this->error('Bible import JSON is invalid: '.$exception->getMessage());
            $this->displayResult(new ImportResult(failures: 1));

            return self::FAILURE;
        }

        $this->displayResult($result);

        return $result->failures === 0 ? self::SUCCESS : self::FAILURE;
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
