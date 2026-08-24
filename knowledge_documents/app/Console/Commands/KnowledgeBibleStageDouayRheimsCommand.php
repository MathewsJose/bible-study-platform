<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Importing\Services\DouayRheimsBibleStagingService;
use Illuminate\Console\Command;

final class KnowledgeBibleStageDouayRheimsCommand extends Command
{
    protected $signature = 'knowledge:bible-stage-douay-rheims
                            {--source=storage/app/imports/staging/bible/douay-rheims/original/repo : Local pinned source repository path}
                            {--output=storage/app/imports/staging/bible/douay-rheims : Staging output directory}
                            {--format=table : Output format: table or json}';

    protected $description = 'Normalize and validate a pinned Original Douay-Rheims source into isolated staging files without importing.';

    public function handle(DouayRheimsBibleStagingService $staging): int
    {
        $result = $staging->stage(
            (string) $this->option('source'),
            (string) $this->option('output'),
        );

        if ($this->option('format') === 'json') {
            $this->line(json_encode($this->jsonResponse($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }

        /** @var array<string, mixed> $manifest */
        $manifest = $result['manifest'];
        /** @var array<string, mixed>|null $audit */
        $audit = $result['audit'];

        $this->info('Douay-Rheims Bible Staging Validation');
        $this->line('Source: '.$manifest['source_name']);
        $this->line('Translation: '.$manifest['translation']);
        $this->line('Edition: '.$manifest['edition']);
        $this->line('Repository commit: '.($manifest['repository_commit'] ?: 'unknown'));
        $this->line('Normalized path: '.$manifest['normalized_path']);
        $this->line('Manifest: '.$result['manifest_file']);
        $this->line('Report: '.$result['report_file']);
        $this->newLine();

        $this->line('Books: '.($audit['summary']['books_found'] ?? 0).' / '.$manifest['expected_books']);
        $this->line('Chapters: '.($audit['summary']['chapters_found'] ?? 0));
        $this->line('Verses: '.($audit['summary']['verses_found'] ?? 0));
        $this->line('Deuterocanonical: '.($audit['summary']['deuterocanonical_books_found'] ?? 0).' / '.count((array) $manifest['expected_deuterocanonical_books']));
        $this->line('Duplicate references: '.(($audit['summary']['duplicate_references'] ?? 0) + ($audit['summary']['duplicate_references_within_source'] ?? 0)));
        $this->line('Original source unchanged: '.($manifest['source_unchanged_during_staging'] ? 'YES' : 'NO'));
        $this->line('Technical validation ready: '.($manifest['technical_validation_ready'] ? 'YES' : 'NO'));
        $this->line('Import readiness: NO');
        $this->line('Decision: '.$manifest['decision']);
        $this->newLine();

        if ($manifest['extra_source_books_excluded'] !== []) {
            $this->warn('Extra source books excluded: '.implode(', ', (array) $manifest['extra_source_books_excluded']));
        }

        if ($manifest['blocking_reasons'] !== []) {
            $this->warn('Blocking reasons:');
            foreach ((array) $manifest['blocking_reasons'] as $reason) {
                $this->warn('- '.$reason);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function jsonResponse(array $result): array
    {
        /** @var array<string, mixed> $audit */
        $audit = $result['audit'] ?? [];
        /** @var array<string, mixed> $manifest */
        $manifest = $result['manifest'];
        unset($manifest['files']);

        return [
            'manifest_file' => $result['manifest_file'],
            'report_file' => $result['report_file'],
            'normalized_path' => $result['normalized_path'],
            'manifest' => $manifest,
            'audit_summary' => $audit['summary'] ?? null,
            'books_missing' => $audit['books_missing'] ?? [],
            'books_unexpected' => $audit['books_unexpected'] ?? [],
            'deuterocanonical' => $audit['deuterocanonical'] ?? null,
            'duplicate_references' => $audit['duplicate_references'] ?? [],
            'malformed_references' => $audit['malformed_references'] ?? [],
            'invalid_chapters' => $audit['invalid_chapters'] ?? [],
            'invalid_verses' => $audit['invalid_verses'] ?? [],
            'empty_verses' => $audit['empty_verses'] ?? [],
        ];
    }
}
