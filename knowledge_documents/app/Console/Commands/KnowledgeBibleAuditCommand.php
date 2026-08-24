<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Services\BibleCorpusAuditService;
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

        if ($this->option('format') === 'json') {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $result['import_ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line('Bible Corpus Audit');
        $this->line('Files: '.$result['summary']['files']);
        $this->line('Expected Catholic books: '.$result['summary']['expected_books']);
        $this->line('Books found: '.$result['summary']['books_found']);
        $this->line('Chapters found: '.$result['summary']['chapters_found']);
        $this->line('Verses found: '.$result['summary']['verses_found']);
        $this->line('Complete Catholic canon: '.($result['summary']['complete_catholic_canon'] ? 'yes' : 'no'));
        $this->line('Import ready: '.($result['import_ready'] ? 'yes' : 'no'));

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
