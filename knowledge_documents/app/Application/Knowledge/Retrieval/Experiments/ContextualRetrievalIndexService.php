<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Infrastructure\Knowledge\Persistence\RetrievalContextualDocumentRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final readonly class ContextualRetrievalIndexService
{
    public function __construct(private ContextualBibleTextBuilder $contextBuilder) {}

    /**
     * @param  array{window?: string|null, batch?: int, force?: bool, source_type?: string|null, dry_run?: bool, limit?: int|null}  $options
     * @return array{processed: int, created: int, updated: int, skipped: int, failed: int, elapsed_ms: int, docs_per_second: float, dry_run: bool, window: string}
     */
    public function build(array $options): array
    {
        $startedAt = microtime(true);
        $window = $this->window($options['window'] ?? 'plus_minus_1');
        $batch = max(1, (int) ($options['batch'] ?? 25));
        $force = (bool) ($options['force'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $sourceType = (string) ($options['source_type'] ?? 'bible_verse');
        $limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $this->sourceQuery($sourceType)
            ->orderBy('id')
            ->chunkById($batch, function (EloquentCollection $documents) use (&$stats, $window, $force, $dryRun, $limit): false|null {
                foreach ($documents as $document) {
                    if ($limit !== null && $stats['processed'] >= $limit) {
                        return false;
                    }

                    $stats['processed']++;

                    try {
                        $payload = $this->payload($document, $window);
                        $existing = RetrievalContextualDocumentRecord::query()
                            ->where('source_document_id', $document->id)
                            ->where('context_window', $window)
                            ->first();

                        if ($existing !== null && ! $force && $existing->context_checksum === $payload['context_checksum']) {
                            $stats['skipped']++;

                            continue;
                        }

                        if ($dryRun) {
                            $existing === null ? $stats['created']++ : $stats['updated']++;

                            continue;
                        }

                        RetrievalContextualDocumentRecord::query()->updateOrCreate(
                            [
                                'source_document_id' => $document->id,
                                'context_window' => $window,
                            ],
                            $payload,
                        );

                        $existing === null ? $stats['created']++ : $stats['updated']++;
                    } catch (\Throwable) {
                        $stats['failed']++;
                    }
                }

                return null;
            }, 'id');

        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            ...$stats,
            'elapsed_ms' => $elapsedMs,
            'docs_per_second' => $this->rate($stats['processed'], $elapsedMs),
            'dry_run' => $dryRun,
            'window' => $window,
        ];
    }

    /**
     * @param  array{window?: string|null}  $options
     * @return array<string, mixed>
     */
    public function status(array $options = []): array
    {
        $query = RetrievalContextualDocumentRecord::query()
            ->when($options['window'] ?? null, fn (Builder $query, string $window): Builder => $query->where('context_window', $this->window($window)));

        $total = (clone $query)->count();
        $embedded = (clone $query)->whereNotNull('embedding')->count();
        $byWindow = RetrievalContextualDocumentRecord::query()
            ->select('context_window', DB::raw('count(*) as total'))
            ->groupBy('context_window')
            ->orderBy('context_window')
            ->pluck('total', 'context_window')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return [
            'total' => $total,
            'embedded' => $embedded,
            'without_embeddings' => $total - $embedded,
            'coverage' => $total === 0 ? 0.0 : round($embedded / $total, 6),
            'by_window' => $byWindow,
            'fingerprint' => $this->fingerprint(),
        ];
    }

    public function fingerprint(): string
    {
        $payload = RetrievalContextualDocumentRecord::query()
            ->select(['source_document_id', 'context_window', 'context_checksum', 'embedding_model', 'embedding_dimensions'])
            ->orderBy('source_document_id')
            ->orderBy('context_window')
            ->get()
            ->map(static fn (RetrievalContextualDocumentRecord $record): array => [
                'source_document_id' => $record->source_document_id,
                'context_window' => $record->context_window,
                'context_checksum' => $record->context_checksum,
                'embedding_model' => $record->embedding_model,
                'embedding_dimensions' => $record->embedding_dimensions,
            ])
            ->all();

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function window(string $window): string
    {
        return match ($window) {
            'verse', 'verse_only' => 'verse',
            'plus_minus_1', 'previous_and_next', 'adjacent' => 'plus_minus_1',
            'plus_minus_3', 'window_3' => 'plus_minus_3',
            'chapter', 'chapter_context' => 'chapter',
            default => $window,
        };
    }

    /**
     * @return Builder<KnowledgeDocumentRecord>
     */
    private function sourceQuery(string $sourceType): Builder
    {
        return KnowledgeDocumentRecord::query()
            ->where('source_type', $sourceType)
            ->when($sourceType === 'bible_verse', fn (Builder $query): Builder => $query->where('source_name', 'Douay-Rheims Bible'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(KnowledgeDocumentRecord $document, string $window): array
    {
        $contextText = $this->contextText($document, $window);
        $metadata = $document->metadata;
        $checksum = hash('sha256', json_encode([
            'source_document_id' => $document->id,
            'source_content' => $document->content,
            'context_window' => $window,
            'context_text' => $contextText,
            'configuration' => ['builder' => self::class, 'version' => 1],
        ], JSON_THROW_ON_ERROR));

        return [
            'source_type' => $document->source_type,
            'source_name' => $document->source_name,
            'reference' => $document->reference,
            'book' => isset($metadata['book']) ? (string) $metadata['book'] : $this->bookFromReference($document->reference),
            'chapter' => isset($metadata['chapter']) && is_numeric($metadata['chapter']) ? (int) $metadata['chapter'] : $this->chapterFromReference($document->reference),
            'verse' => isset($metadata['verse']) && is_numeric($metadata['verse']) ? (int) $metadata['verse'] : $this->verseFromReference($document->reference),
            'document_type' => $document->source_type,
            'context_text' => $contextText,
            'context_checksum' => $checksum,
            'embedding' => null,
            'embedding_provider' => null,
            'embedding_model' => null,
            'embedding_dimensions' => null,
            'embedded_at' => null,
            'embedding_error' => null,
        ];
    }

    private function contextText(KnowledgeDocumentRecord $document, string $window): string
    {
        return match ($window) {
            'verse' => "Target reference: {$document->reference}\nTarget verse: {$document->content}",
            'plus_minus_1' => $this->contextBuilder->build($document, 1),
            'plus_minus_3' => $this->contextBuilder->build($document, 3),
            'chapter' => $this->chapterContext($document),
            default => $this->contextBuilder->build($document, 0),
        };
    }

    private function chapterContext(KnowledgeDocumentRecord $document): string
    {
        $chapterReference = preg_replace('/:\d+$/', '', $document->reference);
        $chapter = KnowledgeDocumentRecord::query()
            ->where('source_type', 'bible_chapter')
            ->where('source_name', $document->source_name)
            ->where('reference', $chapterReference)
            ->first(['reference', 'content']);

        return implode("\n", array_filter([
            'Target reference: '.$document->reference,
            'Target verse: '.$document->content,
            $chapter ? 'Chapter context '.$chapter->reference.': '.$chapter->content : null,
        ]));
    }

    private function bookFromReference(string $reference): ?string
    {
        return preg_match('/^(?<book>.+)\s+\d+:\d+$/', $reference, $matches) ? $matches['book'] : null;
    }

    private function chapterFromReference(string $reference): ?int
    {
        return preg_match('/\s+(?<chapter>\d+):\d+$/', $reference, $matches) ? (int) $matches['chapter'] : null;
    }

    private function verseFromReference(string $reference): ?int
    {
        return preg_match('/\d+:(?<verse>\d+)$/', $reference, $matches) ? (int) $matches['verse'] : null;
    }

    private function rate(int $documents, int $elapsedMs): float
    {
        return $elapsedMs <= 0 ? (float) $documents : round($documents / ($elapsedMs / 1000), 2);
    }
}
