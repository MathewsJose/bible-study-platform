<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Services\EmbeddingVectorValidator;
use App\Infrastructure\Knowledge\Persistence\RetrievalContextualDocumentRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final readonly class ContextualRetrievalEmbeddingService
{
    public function __construct(
        private EmbeddingProviderInterface $provider,
        private EmbeddingVectorValidator $validator,
        private ContextualRetrievalIndexService $index,
    ) {}

    /**
     * @param  array{window?: string|null, batch?: int, force?: bool, dry_run?: bool, limit?: int|null}  $options
     * @return array{processed: int, embedded: int, skipped: int, failed: int, elapsed_ms: int, docs_per_second: float, dry_run: bool, window: string|null}
     */
    public function generate(array $options): array
    {
        $startedAt = microtime(true);
        $window = isset($options['window']) ? $this->index->window((string) $options['window']) : null;
        $batch = max(1, (int) ($options['batch'] ?? 25));
        $force = (bool) ($options['force'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $limit = isset($options['limit']) ? max(1, (int) $options['limit']) : null;
        $stats = [
            'processed' => 0,
            'embedded' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $this->query($window, $force)
            ->orderBy('id')
            ->chunkById($batch, function (EloquentCollection $records) use (&$stats, $dryRun, $limit): false|null {
                $texts = [];
                $targets = [];

                foreach ($records as $record) {
                    if ($limit !== null && $stats['processed'] >= $limit) {
                        return false;
                    }

                    $stats['processed']++;
                    $texts[] = $record->context_text;
                    $targets[] = $record;
                }

                if ($dryRun) {
                    $stats['embedded'] += count($targets);

                    return null;
                }

                try {
                    $embeddings = $this->provider->embedMany($texts);
                } catch (\Throwable $exception) {
                    foreach ($targets as $target) {
                        $this->markFailed($target, $exception->getMessage());
                        $stats['failed']++;
                    }

                    return null;
                }

                foreach ($targets as $index => $target) {
                    try {
                        $embedding = $embeddings[$index] ?? [];
                        $this->validator->validate($embedding);
                        $this->store($target, $embedding);
                        $stats['embedded']++;
                    } catch (\Throwable $exception) {
                        $this->markFailed($target, $exception->getMessage());
                        $stats['failed']++;
                    }
                }

                return null;
            }, 'id');

        $total = $window === null
            ? RetrievalContextualDocumentRecord::query()->count()
            : RetrievalContextualDocumentRecord::query()->where('context_window', $window)->count();
        $stats['skipped'] = max(0, $total - $stats['processed']);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            ...$stats,
            'elapsed_ms' => $elapsedMs,
            'docs_per_second' => $elapsedMs <= 0 ? (float) $stats['processed'] : round($stats['processed'] / ($elapsedMs / 1000), 2),
            'dry_run' => $dryRun,
            'window' => $window,
        ];
    }

    /**
     * @return Builder<RetrievalContextualDocumentRecord>
     */
    private function query(?string $window, bool $force): Builder
    {
        return RetrievalContextualDocumentRecord::query()
            ->when($window !== null, fn (Builder $query): Builder => $query->where('context_window', $window))
            ->when(! $force, fn (Builder $query): Builder => $query->whereNull('embedding'));
    }

    /**
     * @param  list<float>  $embedding
     */
    private function store(RetrievalContextualDocumentRecord $record, array $embedding): void
    {
        $values = [
            'embedding_provider' => (string) config('embeddings.provider', 'null'),
            'embedding_model' => $this->provider->identifier(),
            'embedding_dimensions' => count($embedding),
            'embedded_at' => now(),
            'embedding_error' => null,
            'updated_at' => now(),
        ];

        if (DB::getDriverName() !== 'pgsql') {
            RetrievalContextualDocumentRecord::query()
                ->whereKey($record->id)
                ->update([
                    ...$values,
                    'embedding' => $this->formatEmbedding($embedding),
                ]);

            return;
        }

        $table = (new RetrievalContextualDocumentRecord())->getTable();

        DB::update(
            "update {$table} set embedding = ?::vector, embedding_provider = ?, embedding_model = ?, embedding_dimensions = ?, embedded_at = ?, embedding_error = ?, updated_at = ? where id = ?",
            [
                $this->formatEmbedding($embedding),
                $values['embedding_provider'],
                $values['embedding_model'],
                $values['embedding_dimensions'],
                $values['embedded_at'],
                $values['embedding_error'],
                $values['updated_at'],
                $record->id,
            ],
        );
    }

    private function markFailed(RetrievalContextualDocumentRecord $record, string $message): void
    {
        RetrievalContextualDocumentRecord::query()
            ->whereKey($record->id)
            ->update([
                'embedding_error' => mb_substr($message, 0, 4000),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  list<float>  $embedding
     */
    private function formatEmbedding(array $embedding): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return '['.implode(',', $embedding).']';
        }

        return json_encode($embedding, JSON_THROW_ON_ERROR);
    }
}
