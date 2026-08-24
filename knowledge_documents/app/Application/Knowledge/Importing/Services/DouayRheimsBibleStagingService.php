<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Services;

use App\Infrastructure\Knowledge\Importing\BibleCanon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

final readonly class DouayRheimsBibleStagingService
{
    private const SOURCE_ID = 'bible.original_douay_rheims_1582_1610';

    private const SOURCE_URL = 'https://github.com/janvier-s/original-douay-rheims';

    /**
     * @var array<string, string>
     */
    private const SOURCE_BOOKS = [
        'Genesis' => 'genesis',
        'Exodus' => 'exodus',
        'Leviticus' => 'leviticus',
        'Numbers' => 'numbers',
        'Deuteronomy' => 'deuteronomy',
        'Joshua' => 'josue',
        'Judges' => 'judges',
        'Ruth' => 'ruth',
        '1 Samuel' => '1-kings',
        '2 Samuel' => '2-kings',
        '1 Kings' => '3-kings',
        '2 Kings' => '4-kings',
        '1 Chronicles' => '1-paralipomenon',
        '2 Chronicles' => '2-paralipomenon',
        'Ezra' => '1-esdras',
        'Nehemiah' => '2-esdras',
        'Tobit' => 'tobias',
        'Judith' => 'judith',
        'Esther' => 'esther',
        '1 Maccabees' => '1-machabees',
        '2 Maccabees' => '2-machabees',
        'Job' => 'job',
        'Psalms' => 'psalms',
        'Proverbs' => 'proverbs',
        'Ecclesiastes' => 'ecclesiastes',
        'Song of Songs' => 'canticle-of-canticles',
        'Wisdom' => 'wisdom',
        'Sirach' => 'ecclesiasticus',
        'Isaiah' => 'isaie',
        'Jeremiah' => 'jeremie',
        'Lamentations' => 'lamentations',
        'Baruch' => 'baruch',
        'Ezekiel' => 'ezechiel',
        'Daniel' => 'daniel',
        'Hosea' => 'osee',
        'Joel' => 'joel',
        'Amos' => 'amos',
        'Obadiah' => 'abdias',
        'Jonah' => 'jonas',
        'Micah' => 'micheas',
        'Nahum' => 'nahum',
        'Habakkuk' => 'habacuc',
        'Zephaniah' => 'sophonias',
        'Haggai' => 'aggeus',
        'Zechariah' => 'zacharias',
        'Malachi' => 'malachie',
        'Matthew' => 'matthew',
        'Mark' => 'mark',
        'Luke' => 'luke',
        'John' => 'john',
        'Acts' => 'acts',
        'Romans' => 'romans',
        '1 Corinthians' => '1-corinthians',
        '2 Corinthians' => '2-corinthians',
        'Galatians' => 'galatians',
        'Ephesians' => 'ephesians',
        'Philippians' => 'philippians',
        'Colossians' => 'colossians',
        '1 Thessalonians' => '1-thessalonians',
        '2 Thessalonians' => '2-thessalonians',
        '1 Timothy' => '1-timothy',
        '2 Timothy' => '2-timothy',
        'Titus' => 'titus',
        'Philemon' => 'philemon',
        'Hebrews' => 'hebrews',
        'James' => 'james',
        '1 Peter' => '1-peter',
        '2 Peter' => '2-peter',
        '1 John' => '1-john',
        '2 John' => '2-john',
        '3 John' => '3-john',
        'Jude' => 'jude',
        'Revelation' => 'apocalypse',
    ];

    public function __construct(
        private BibleCanon $canon,
        private BibleCorpusAuditService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function stage(string $sourcePath, string $stagingPath): array
    {
        $sourcePath = $this->normalizePath($sourcePath);
        $stagingPath = $this->normalizePath($stagingPath);
        $rawPath = $sourcePath.DIRECTORY_SEPARATOR.'bible'.DIRECTORY_SEPARATOR.'raw';
        $normalizedPath = $stagingPath.DIRECTORY_SEPARATOR.'normalized';
        $manifestPath = $stagingPath.DIRECTORY_SEPARATOR.'manifest';
        $reportsPath = $stagingPath.DIRECTORY_SEPARATOR.'reports';
        $errors = [];

        File::ensureDirectoryExists($normalizedPath);
        File::ensureDirectoryExists($manifestPath);
        File::ensureDirectoryExists($reportsPath);
        $this->clearJsonFiles($normalizedPath);

        if (! is_dir($sourcePath)) {
            $errors[] = "Source repository directory does not exist: {$sourcePath}";
        }

        if (! is_dir($rawPath)) {
            $errors[] = "Source raw Bible directory does not exist: {$rawPath}";
        }

        $sourceChecksumBefore = is_dir($sourcePath) ? $this->directoryChecksum($sourcePath) : null;
        $repositoryCommit = $this->gitCommit($sourcePath);
        $normalizedFiles = [];
        $files = [];
        $missingSourceFiles = [];
        $excludedSourceRecords = [];

        if ($errors === []) {
            foreach ($this->canon->books() as $canonicalBook) {
                $sourceSlug = self::SOURCE_BOOKS[$canonicalBook] ?? null;
                $sourceFile = $sourceSlug === null ? null : $rawPath.DIRECTORY_SEPARATOR.$sourceSlug.'.json';

                if ($sourceFile === null || ! is_file($sourceFile)) {
                    $missingSourceFiles[] = $canonicalBook;

                    continue;
                }

                $normalizedFile = $normalizedPath.DIRECTORY_SEPARATOR.$this->fileSlug($canonicalBook).'.json';
                $payload = $this->readJsonFile($sourceFile);
                $normalized = $this->normalizeBookPayload($payload, $canonicalBook, $sourceSlug, $repositoryCommit, $excludedSourceRecords);

                File::put($normalizedFile, json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $normalizedFiles[] = $normalizedFile;
                $files[] = [
                    'book' => $canonicalBook,
                    'source_file' => $this->relativePath($sourceFile),
                    'normalized_file' => $this->relativePath($normalizedFile),
                    'source_checksum' => hash_file('sha256', $sourceFile) ?: null,
                    'normalized_checksum' => hash_file('sha256', $normalizedFile) ?: null,
                    'chapters' => count((array) ($normalized['chapters'] ?? [])),
                    'verses' => $this->verseCount((array) ($normalized['chapters'] ?? [])),
                ];
            }
        }

        $sourceChecksumAfter = is_dir($sourcePath) ? $this->directoryChecksum($sourcePath) : null;
        $audit = $normalizedFiles === [] ? null : $this->audit->audit($normalizedFiles);
        $technicalReady = $this->technicalReady($audit, $missingSourceFiles, $errors);
        $extraSourceBooks = is_dir($rawPath) ? $this->extraSourceBooks($rawPath) : [];

        $manifest = [
            'source_id' => self::SOURCE_ID,
            'source_name' => 'Original Douay-Rheims Bible',
            'translation' => 'Original Douay-Rheims Bible',
            'edition' => '1582 New Testament / 1609-1610 Old Testament JSON dataset',
            'language' => 'en',
            'source_url' => self::SOURCE_URL,
            'repository_url' => self::SOURCE_URL,
            'repository_commit' => $repositoryCommit,
            'license' => 'CC0 1.0 Universal public domain dedication claimed by source repository',
            'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'copyright_status' => 'requires_verification',
            'verification_status' => 'requires_verification',
            'import_allowed' => false,
            'rights_notes' => 'Repository claims CC0/public-domain status. Manual verification is still required for repository ownership, transcription provenance, source edition lineage, and redistribution suitability before import.',
            'format' => 'normalized single-book JSON derived from pinned book-level raw JSON',
            'source_path' => $this->relativePath($sourcePath),
            'normalized_path' => $this->relativePath($normalizedPath),
            'source_checksum_before' => $sourceChecksumBefore,
            'source_checksum_after' => $sourceChecksumAfter,
            'source_unchanged_during_staging' => $sourceChecksumBefore !== null && $sourceChecksumBefore === $sourceChecksumAfter,
            'content_checksum' => $normalizedFiles === [] ? null : $this->filesChecksum($normalizedFiles),
            'expected_books' => count($this->canon->books()),
            'expected_deuterocanonical_books' => $this->canon->deuterocanonicalBooks(),
            'staged_books' => count($files),
            'missing_source_files' => $missingSourceFiles,
            'extra_source_books_excluded' => $extraSourceBooks,
            'excluded_source_records' => $excludedSourceRecords,
            'files' => $files,
            'technical_validation_ready' => $technicalReady,
            'import_readiness' => false,
            'decision' => $technicalReady ? 'BLOCKED - PROVENANCE' : 'BLOCKED - CORPUS',
            'blocking_reasons' => $this->blockingReasons($audit, $missingSourceFiles, $errors),
            'created_at' => now()->toIso8601String(),
        ];

        $report = [
            'manifest' => $manifest,
            'audit' => $audit,
        ];

        $manifestFile = $manifestPath.DIRECTORY_SEPARATOR.'source-manifest.json';
        $reportFile = $reportsPath.DIRECTORY_SEPARATOR.'validation-report.json';
        File::put($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        File::put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return [
            'manifest_file' => $manifestFile,
            'report_file' => $reportFile,
            'normalized_path' => $normalizedPath,
            'manifest' => $manifest,
            'audit' => $audit,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array{book: string, chapter: int, reason: string}>  $excludedSourceRecords
     * @return array<string, mixed>
     */
    private function normalizeBookPayload(array $payload, string $canonicalBook, string $sourceSlug, ?string $repositoryCommit, array &$excludedSourceRecords): array
    {
        $chapters = [];

        foreach ((array) ($payload['chapters'] ?? []) as $chapter) {
            if (! is_array($chapter)) {
                continue;
            }

            $chapterNumber = (int) ($chapter['chapter'] ?? $chapter['number'] ?? 0);
            if ($chapterNumber < 1) {
                $excludedSourceRecords[] = [
                    'book' => $canonicalBook,
                    'chapter' => $chapterNumber,
                    'reason' => 'Non-canonical source prologue or introductory chapter was excluded from normalized Bible chapter data.',
                ];

                continue;
            }

            $chapters[] = $this->normalizeChapter($chapter);
        }

        return [
            'translation' => 'original-douay-rheims',
            'language' => 'en',
            'source_url' => self::SOURCE_URL,
            'license' => 'CC0 1.0 Universal public domain dedication claimed by source repository',
            'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'source_edition' => 'Original Douay-Rheims 1582 New Testament / 1609-1610 Old Testament',
            'source_identifier' => self::SOURCE_ID,
            'source_version' => $repositoryCommit === null ? null : 'git:'.$repositoryCommit,
            'copyright_status' => 'requires_verification',
            'verification_status' => 'requires_verification',
            'book' => $canonicalBook,
            'source_book_slug' => $sourceSlug,
            'source_book_title' => $payload['book_title'] ?? null,
            'source_short_title' => $payload['short_title'] ?? null,
            'chapters' => $chapters,
        ];
    }

    /**
     * @param  array<string, mixed>  $chapter
     * @return array<string, mixed>
     */
    private function normalizeChapter(array $chapter): array
    {
        return [
            'chapter' => (int) ($chapter['chapter'] ?? $chapter['number'] ?? 0),
            'summary' => $chapter['summary'] ?? null,
            'verses' => array_values(array_map(
                fn (array $verse): array => $this->normalizeVerse($verse),
                array_filter((array) ($chapter['verses'] ?? []), 'is_array'),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $verse
     * @return array<string, mixed>
     */
    private function normalizeVerse(array $verse): array
    {
        $normalized = [
            'verse' => (int) ($verse['verse'] ?? $verse['number'] ?? 0),
            'text' => (string) ($verse['text'] ?? $verse['content'] ?? ''),
        ];

        if (isset($verse['cross_refs'])) {
            $normalized['cross_references_raw'] = $verse['cross_refs'];
        }

        if (isset($verse['notes'])) {
            $normalized['notes'] = $verse['notes'];
        }

        if (isset($verse['has_annotation'])) {
            $normalized['has_annotation'] = (bool) $verse['has_annotation'];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        try {
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException("Unable to decode {$path}: {$exception->getMessage()}", previous: $exception);
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  list<string>  $files
     */
    private function filesChecksum(array $files): string
    {
        sort($files);
        $context = hash_init('sha256');

        foreach ($files as $file) {
            hash_update($context, $this->relativePath($file));
            hash_update($context, "\n");
            hash_update_file($context, $file);
            hash_update($context, "\n");
        }

        return hash_final($context);
    }

    private function directoryChecksum(string $directory): string
    {
        $files = [];
        $finder = new Finder();
        $finder->files()->in($directory)->ignoreDotFiles(false)->exclude('.git')->sortByName();

        foreach ($finder as $file) {
            $files[] = $file->getRealPath() ?: $file->getPathname();
        }

        return $this->filesChecksum($files);
    }

    private function gitCommit(string $repositoryPath): ?string
    {
        if (! is_dir($repositoryPath.DIRECTORY_SEPARATOR.'.git')) {
            return $this->manifestRepositoryCommit();
        }

        $process = new Process(['git', '-C', $repositoryPath, 'rev-parse', 'HEAD']);
        $process->run();

        if (! $process->isSuccessful()) {
            return $this->manifestRepositoryCommit();
        }

        return trim($process->getOutput()) ?: $this->manifestRepositoryCommit();
    }

    private function manifestRepositoryCommit(): ?string
    {
        $manifestPath = base_path('docs/source-manifests/original-douay-rheims-1582-1610.json');
        if (! is_file($manifestPath)) {
            return null;
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $commit = $manifest['repository_commit'] ?? null;

        return is_string($commit) && $commit !== '' ? $commit : null;
    }

    /**
     * @return list<string>
     */
    private function extraSourceBooks(string $rawPath): array
    {
        $expected = array_values(self::SOURCE_BOOKS);
        $found = [];
        $finder = new Finder();
        $finder->files()->in($rawPath)->name('*.json')->sortByName();

        foreach ($finder as $file) {
            $found[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }

        return array_values(array_diff($found, $expected));
    }

    /**
     * @param  array<string, mixed>|null  $audit
     * @param  list<string>  $missingSourceFiles
     * @param  list<string>  $errors
     */
    private function technicalReady(?array $audit, array $missingSourceFiles, array $errors): bool
    {
        if ($audit === null || $missingSourceFiles !== [] || $errors !== []) {
            return false;
        }

        return (bool) ($audit['summary']['complete_catholic_canon'] ?? false)
            && ($audit['summary']['duplicate_references'] ?? 1) === 0
            && ($audit['summary']['duplicate_references_within_source'] ?? 1) === 0
            && ($audit['summary']['malformed_references'] ?? 1) === 0
            && ($audit['invalid_chapters'] ?? []) === []
            && ($audit['invalid_verses'] ?? []) === []
            && ($audit['empty_verses'] ?? []) === [];
    }

    /**
     * @param  array<string, mixed>|null  $audit
     * @param  list<string>  $missingSourceFiles
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function blockingReasons(?array $audit, array $missingSourceFiles, array $errors): array
    {
        $reasons = $errors;

        if ($missingSourceFiles !== []) {
            $reasons[] = 'Missing source files for canonical books: '.implode(', ', $missingSourceFiles);
        }

        if ($audit !== null && ! (bool) ($audit['summary']['complete_catholic_canon'] ?? false)) {
            $reasons[] = 'Normalized files are not a complete Catholic canon.';
        }

        foreach (['duplicate_references', 'duplicate_references_within_source', 'malformed_references', 'invalid_chapters', 'invalid_verses', 'empty_verses'] as $key) {
            if ($audit !== null && ($audit[$key] ?? []) !== []) {
                $reasons[] = str_replace('_', ' ', $key).': '.implode(', ', array_slice((array) $audit[$key], 0, 20));
            }
        }

        $reasons[] = 'Manual provenance and licensing verification is required before import.';

        return array_values(array_unique($reasons));
    }

    private function verseCount(array $chapters): int
    {
        return array_sum(array_map(static fn (array $chapter): int => count((array) ($chapter['verses'] ?? [])), $chapters));
    }

    private function clearJsonFiles(string $directory): void
    {
        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'json') {
                File::delete($file->getPathname());
            }
        }
    }

    private function fileSlug(string $book): string
    {
        return Str::of($book)->lower()->replace(' ', '-')->toString();
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function relativePath(string $path): string
    {
        return Str::of($path)->replace(base_path().DIRECTORY_SEPARATOR, '')->replace('\\', '/')->toString();
    }
}
