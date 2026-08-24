<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use App\Application\Knowledge\Importing\Services\ProvenanceGate;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use App\Application\Knowledge\Importing\Services\SourceInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

final class KnowledgeVerifyCommand extends Command
{
    protected $signature = 'knowledge:verify
                            {source=all : Source identifier to verify, or all}
                            {--source-id= : Source inventory identifier to verify}
                            {--format=table : Output format: table or json}';

    protected $description = 'Validate configured knowledge import files without persisting documents.';

    public function handle(KnowledgeSourceRegistry $sources, SourceInventory $inventory, ProvenanceGate $gate): int
    {
        $source = (string) $this->argument('source');
        $sourceId = $this->option('source-id') !== null ? (string) $this->option('source-id') : null;
        $format = (string) $this->option('format');
        $verified = 0;
        $failed = 0;
        $unsupported = 0;
        $blocked = 0;
        $requiresVerification = 0;
        $approved = 0;
        $results = [];

        foreach ($this->collectFiles((array) config('knowledge.import.directories', [])) as $file) {
            $path = $file->getRealPath();
            if ($path === false) {
                continue;
            }

            $importer = $this->resolveImporter($sources, $source, $path);
            if (! $importer instanceof KnowledgeImporterInterface) {
                $unsupported++;
                $results[] = [
                    'file' => $path,
                    'source' => null,
                    'status' => 'unsupported',
                    'import_allowed' => false,
                    'validation_errors' => [],
                    'warnings' => ['No registered importer supports this file.'],
                ];
                continue;
            }

            $raw = $importer->fetch($path);
            $validation = $importer->validate($raw);
            $gateResult = $gate->evaluate($importer, $sourceId, $raw->metadata);
            $inventorySource = $gateResult->source;

            if ($gateResult->allowed) {
                $approved++;
            } else {
                $blocked++;
            }

            if (($inventorySource?->verificationStatus->value ?? null) === 'requires_verification') {
                $requiresVerification++;
            }

            if ($validation->valid) {
                $verified++;
            } else {
                $failed++;
                foreach ($validation->errors as $error) {
                    $this->error("{$path}: {$error}");
                }
            }

            $results[] = [
                'file' => $path,
                'source' => $inventorySource?->toArray(),
                'status' => $gateResult->allowed && $validation->valid ? 'verified' : 'blocked',
                'source_type' => $importer->identifier(),
                'license' => $inventorySource?->license,
                'copyright_status' => $inventorySource?->copyrightStatus->value ?? 'requires_verification',
                'source_url' => $inventorySource?->sourceUrl,
                'license_url' => $inventorySource?->licenseUrl,
                'import_allowed' => $gateResult->allowed,
                'validation_errors' => $validation->errors,
                'warnings' => $gateResult->warnings,
                'gate_errors' => $gateResult->errors,
            ];
        }

        foreach ($inventory->all() as $inventorySource) {
            if ($source !== 'all' && $inventorySource->type !== $this->normalizeSourceIdentifier($source)) {
                continue;
            }

            if ($sourceId !== null && $inventorySource->id !== $sourceId) {
                continue;
            }

            if (! collect($results)->contains(fn (array $result): bool => ($result['source']['id'] ?? null) === $inventorySource->id)) {
                $allowed = $inventorySource->importAllowed
                    && $inventorySource->verificationStatus->value === 'approved'
                    && $inventorySource->copyrightStatus->importable();

                $results[] = [
                    'file' => null,
                    'source' => $inventorySource->toArray(),
                    'status' => $allowed ? 'verified' : $inventorySource->verificationStatus->value,
                    'source_type' => $inventorySource->type,
                    'license' => $inventorySource->license,
                    'copyright_status' => $inventorySource->copyrightStatus->value,
                    'source_url' => $inventorySource->sourceUrl,
                    'license_url' => $inventorySource->licenseUrl,
                    'import_allowed' => $allowed,
                    'validation_errors' => [],
                    'warnings' => array_filter([
                        $inventorySource->license === null ? 'License information is missing; no license has been inferred.' : null,
                        $inventorySource->copyrightStatus->value === 'requires_verification' ? 'Copyright status requires manual verification; do not assume redistribution rights.' : null,
                    ]),
                    'gate_errors' => $allowed ? [] : ['Source is not currently approved for import.'],
                ];

                if ($allowed) {
                    $approved++;
                } else {
                    $blocked++;
                }

                if ($inventorySource->verificationStatus->value === 'requires_verification') {
                    $requiresVerification++;
                }
            }
        }

        $payload = [
            'overall_status' => $failed === 0 && $blocked === 0 ? 'passed' : 'blocked',
            'counts' => [
                'sources' => count($results),
                'approved' => $approved,
                'blocked' => $blocked,
                'requires_verification' => $requiresVerification,
                'verified_files' => $verified,
                'unsupported_files' => $unsupported,
                'failed_files' => $failed,
            ],
            'results' => array_values($results),
        ];

        if ($format === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $failed === 0 && $blocked === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Source', 'Status', 'Source Type', 'License', 'Copyright Status', 'Source URL', 'License URL', 'Import Allowed', 'Warnings'],
            array_map(static fn (array $result): array => [
                (string) ($result['source']['name'] ?? $result['file'] ?? 'Unknown'),
                (string) $result['status'],
                (string) ($result['source_type'] ?? ''),
                (string) ($result['license'] ?? ''),
                (string) ($result['copyright_status'] ?? ''),
                (string) ($result['source_url'] ?? ''),
                (string) ($result['license_url'] ?? ''),
                $result['import_allowed'] ? 'yes' : 'no',
                implode(' ', array_merge((array) $result['warnings'], (array) $result['gate_errors'], (array) $result['validation_errors'])),
            ], $results),
        );

        $this->line("verified: {$verified}");
        $this->line("unsupported: {$unsupported}");
        $this->line("failed: {$failed}");
        $this->line("approved: {$approved}");
        $this->line("blocked: {$blocked}");
        $this->line("requires verification: {$requiresVerification}");

        return $failed === 0 && $blocked === 0 ? self::SUCCESS : self::FAILURE;
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
        $source = $this->normalizeSourceIdentifier($source);

        if ($source !== 'all') {
            $importer = $sources->resolve($source);

            return $importer->supports($path) ? $importer : null;
        }

        return $sources->detect($path);
    }

    private function normalizeSourceIdentifier(string $source): string
    {
        return match ($source) {
            'church-fathers', 'church-father' => 'church_fathers',
            default => $source,
        };
    }
}
