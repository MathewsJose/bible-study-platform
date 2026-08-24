<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Services\BibleCorpusAuditService;
use App\Application\Knowledge\Importing\DTOs\ProvenanceGateResult;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;
use App\Application\Knowledge\Importing\Services\ProvenanceGate;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

final class KnowledgeBibleAuditCommand extends Command
{
    protected $signature = 'knowledge:bible-audit
                            {--path=* : Specific Bible source file path to audit}
                            {--source-id= : Source inventory identifier for provenance status}
                            {--format=table : Output format: table or json}';

    protected $description = 'Audit Bible source files for Catholic canon readiness without importing data.';

    public function handle(
        BibleCorpusAuditService $audit,
        KnowledgeSourceRegistry $sources,
        ProvenanceGate $gate,
    ): int {
        $paths = $this->paths($sources);
        $result = $audit->audit($paths);
        $gateResult = $gate->evaluate($sources->resolve('bible'), $this->option('source-id') ? (string) $this->option('source-id') : null);
        $result['provenance'] = $gateResult->toArray();
        $result['import_ready'] = $gateResult->allowed && (bool) $result['summary']['complete_catholic_canon'];
        $result['readiness'] = $this->readiness($result, $gateResult);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $result['import_ready'] ? self::SUCCESS : self::FAILURE;
        }

        $readiness = $result['readiness'];

        $this->line('BIBLE SOURCE READINESS');
        $this->line('Source: '.$this->display($readiness['source']['name']));
        $this->line('Translation: '.$this->display($readiness['source']['translation']));
        $this->line('Edition: '.$this->display($readiness['source']['edition']));
        $this->line('Format: '.$this->display($readiness['source']['format']));
        $this->newLine();
        $this->line('Books:');
        $this->line('Expected: '.$readiness['books']['expected']);
        $this->line('Found: '.$readiness['books']['found']);
        $this->newLine();
        $this->line('Chapters:');
        $this->line('Found: '.$readiness['chapters']['found']);
        $this->newLine();
        $this->line('Verses:');
        $this->line('Found: '.$readiness['verses']['found']);
        $this->newLine();
        $this->line('Deuterocanonical:');
        $this->line('Expected: '.$readiness['deuterocanonical']['expected']);
        $this->line('Found: '.$readiness['deuterocanonical']['found']);
        $this->newLine();
        $this->line('Duplicate references: '.$readiness['duplicate_references']);
        $this->newLine();
        $this->line('Provenance:');
        $this->line('Source URL: '.$this->display($readiness['provenance']['source_url']));
        $this->line('License: '.$this->display($readiness['provenance']['license']));
        $this->line('License URL: '.$this->display($readiness['provenance']['license_url']));
        $this->line('Copyright: '.$this->display($readiness['provenance']['copyright']));
        $this->line('Verification: '.$this->display($readiness['provenance']['verification']));
        $this->newLine();
        $this->line('Import readiness: '.($readiness['import_ready'] ? 'YES' : 'NO'));

        if ($readiness['blocking_reasons'] !== []) {
            $this->warn('Blocking reasons:');
            foreach ($readiness['blocking_reasons'] as $reason) {
                $this->warn('- '.$reason);
            }
        }

        $this->table(['Book', 'Chapters', 'Verses'], array_map(
            static fn (string $book, array $counts): array => [$book, $counts['chapters'], $counts['verses']],
            array_keys($result['book_counts']),
            array_values($result['book_counts']),
        ));

        if ($result['books_missing'] !== []) {
            $this->warn('Missing books: '.implode(', ', $result['books_missing']));
        }

        if ($result['books_unexpected'] !== []) {
            $this->warn('Unexpected books: '.implode(', ', $result['books_unexpected']));
        }

        if ($result['deuterocanonical']['missing'] !== []) {
            $this->warn('Missing deuterocanonical books: '.implode(', ', $result['deuterocanonical']['missing']));
        }

        foreach ([
            'duplicate_references_within_source',
            'malformed_references',
            'invalid_chapters',
            'invalid_verses',
            'empty_verses',
            'suspiciously_short_verses',
            'suspiciously_long_verses',
            'invalid_canonical_ordering',
            'source_identity_warnings',
        ] as $key) {
            if ($result[$key] !== []) {
                $this->warn(str_replace('_', ' ', $key).': '.implode(', ', $result[$key]));
            }
        }

        foreach ($gateResult->errors as $error) {
            $this->error($error);
        }

        foreach ($gateResult->warnings as $warning) {
            $this->warn($warning);
        }

        return $result['import_ready'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function readiness(array $result, ProvenanceGateResult $gateResult): array
    {
        $sourceSummary = $result['source_summary'];
        $source = $gateResult->source;
        $blockingReasons = [];

        foreach ([
            'books_missing' => 'Missing books: ',
            'books_unexpected' => 'Unexpected books: ',
            'duplicate_references_within_source' => 'Duplicate references within source: ',
            'malformed_references' => 'Malformed references: ',
            'invalid_chapters' => 'Invalid chapters: ',
            'invalid_verses' => 'Invalid verses: ',
            'empty_verses' => 'Empty verses: ',
            'source_identity_warnings' => '',
        ] as $key => $prefix) {
            if ($result[$key] !== []) {
                $blockingReasons[] = $prefix.implode(', ', array_slice($this->strings($result[$key]), 0, 20));
            }
        }

        foreach ($gateResult->errors as $error) {
            $blockingReasons[] = $error;
        }

        return [
            'source' => [
                'name' => $source?->name,
                'translation' => $sourceSummary['translation'],
                'edition' => $sourceSummary['source_edition'] ?? $source?->edition,
                'format' => $sourceSummary['format'],
            ],
            'books' => [
                'expected' => $result['summary']['expected_books'],
                'found' => $result['summary']['books_found'],
            ],
            'chapters' => [
                'found' => $result['summary']['chapters_found'],
            ],
            'verses' => [
                'found' => $result['summary']['verses_found'],
            ],
            'deuterocanonical' => [
                'expected' => count($result['deuterocanonical']['expected']),
                'found' => count($result['deuterocanonical']['found']),
            ],
            'duplicate_references' => $result['summary']['duplicate_references'] + $result['summary']['duplicate_references_within_source'],
            'provenance' => [
                'source_url' => $sourceSummary['source_url'] ?? $source?->sourceUrl,
                'license' => $sourceSummary['license'] ?? $source?->license,
                'license_url' => $sourceSummary['license_url'] ?? $source?->licenseUrl,
                'copyright' => $source?->copyrightStatus->value,
                'verification' => $source?->verificationStatus->value,
            ],
            'import_ready' => $result['import_ready'],
            'blocking_reasons' => array_values(array_unique(array_filter(
                $blockingReasons,
                static fn (string $reason): bool => trim($reason) !== '',
            ))),
        ];
    }

    private function display(mixed $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'unknown' : $value;
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => (string) $item,
            is_array($value) ? $value : [],
        ));
    }

    /**
     * @return list<string>
     */
    private function paths(KnowledgeSourceRegistry $sources): array
    {
        $explicitPaths = array_values(array_filter(array_map(
            static fn (mixed $path): string => (string) $path,
            (array) $this->option('path'),
        ), static fn (string $path): bool => $path !== ''));

        if ($explicitPaths !== []) {
            return array_map(fn (string $path): string => $this->resolvePath($path), $explicitPaths);
        }

        $importer = $sources->resolve('bible');
        $files = new Collection();

        foreach ((array) config('knowledge.import.directories', []) as $directory) {
            $resolved = $this->resolvePath((string) $directory);
            if (! is_dir($resolved)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($resolved)->name('*.json')->sortByName();

            foreach ($finder as $file) {
                $path = $file->getRealPath();
                if ($path !== false && $importer->supports($path)) {
                    $files->push($path);
                }
            }
        }

        return $files->values()->all();
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Z]:[\\\\\/]/i', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
