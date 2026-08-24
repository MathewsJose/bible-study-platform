<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Services;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use App\Application\Knowledge\Importing\DTOs\ImportPipelineResult;
use App\Application\Knowledge\Services\EmbeddingGenerationService;
use App\Infrastructure\Knowledge\Importers\ImportManifest;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ImportPipeline
{
    public function __construct(
        private KnowledgeDocumentPersistenceService $persistence,
        private EmbeddingGenerationService $embeddings,
        private ProvenanceGate $provenanceGate,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{force?: bool, skip_unchanged?: bool, queue_embeddings?: bool, source_id?: string|null, allow_unverified_source?: bool}  $options
     */
    public function import(KnowledgeImporterInterface $importer, string $path, array $metadata = [], array $options = []): ImportPipelineResult
    {
        $started = microtime(true);
        $force = (bool) ($options['force'] ?? false);
        $skipUnchanged = (bool) ($options['skip_unchanged'] ?? true);
        $queueEmbeddings = (bool) ($options['queue_embeddings'] ?? true);
        $checksum = hash_file('sha256', $path) ?: '';
        $gate = $this->provenanceGate->evaluate(
            importer: $importer,
            sourceId: isset($options['source_id']) ? (string) $options['source_id'] : null,
            metadata: $metadata,
            allowUnsafeOverride: (bool) ($options['allow_unverified_source'] ?? false),
        );

        if (! $gate->allowed) {
            Log::warning('Knowledge import blocked by provenance gate.', [
                'source' => $importer->identifier(),
                'path' => $path,
                'errors' => $gate->errors,
                'warnings' => $gate->warnings,
            ]);

            return new ImportPipelineResult(
                failed: 1,
                durationSeconds: $this->duration($started),
                errors: $gate->errors,
            );
        }

        $metadata = array_merge($metadata, $gate->provenance?->toMetadata() ?? []);

        if ($skipUnchanged && ! $force && $this->alreadyImported($importer, $path, $checksum)) {
            Log::info('Knowledge import skipped unchanged source file.', [
                'source' => $importer->identifier(),
                'path' => $path,
                'checksum' => $checksum,
            ]);

            return new ImportPipelineResult(skipped: 1, durationSeconds: $this->duration($started));
        }

        $manifest = $this->startManifest($importer, $path, $checksum, $metadata);

        try {
            $rawDocument = $importer->fetch($path, $metadata);
            $validation = $importer->validate($rawDocument);

            if (! $validation->valid) {
                $result = new ImportPipelineResult(
                    failed: 1,
                    durationSeconds: $this->duration($started),
                    errors: $validation->errors,
                );
                $this->failManifest($manifest, implode(' ', $validation->errors));

                return $result;
            }

            $normalized = $importer->normalize($rawDocument);
            if (isset($normalized[0])) {
                $manifest->update([
                    'source_name' => $normalized[0]->sourceName,
                    'language' => $normalized[0]->language,
                ]);
            }

            $persistence = $this->persistence->persist($normalized);
            /** @var ImportResult $importResult */
            $importResult = $persistence['result'];
            /** @var list<string> $changedDocumentIds */
            $changedDocumentIds = $persistence['changed_document_ids'];
            /** @var list<string> $errors */
            $errors = $persistence['errors'];

            $embeddingsQueued = 0;
            if ($queueEmbeddings && $changedDocumentIds !== []) {
                $dispatch = $this->embeddings->dispatchDocumentIds($changedDocumentIds);
                $embeddingsQueued = $dispatch->documentsQueued;
            }

            $this->finishManifest($manifest, $importResult, $errors);

            $result = new ImportPipelineResult(
                created: $importResult->created,
                updated: $importResult->updated,
                skipped: $importResult->skipped,
                failed: $importResult->failures,
                embeddingsQueued: $embeddingsQueued,
                durationSeconds: $this->duration($started),
                errors: $errors,
            );

            Log::info('Knowledge import completed.', [
                'source' => $importer->identifier(),
                'path' => $path,
                'created' => $result->created,
                'updated' => $result->updated,
                'skipped' => $result->skipped,
                'failed' => $result->failed,
                'embeddings_queued' => $result->embeddingsQueued,
                'duration_seconds' => $result->durationSeconds,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return $result;
        } catch (Throwable $exception) {
            $this->failManifest($manifest, $exception->getMessage());

            Log::error('Knowledge import failed.', [
                'source' => $importer->identifier(),
                'path' => $path,
                'exception' => $exception,
            ]);

            return new ImportPipelineResult(
                failed: 1,
                durationSeconds: $this->duration($started),
                errors: [$exception->getMessage()],
            );
        }
    }

    private function alreadyImported(KnowledgeImporterInterface $importer, string $path, string $checksum): bool
    {
        return ImportManifest::query()
            ->where('file_path', $path)
            ->where('checksum', $checksum)
            ->where('source_type', $importer->identifier())
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function startManifest(KnowledgeImporterInterface $importer, string $path, string $checksum, array $metadata): ImportManifest
    {
        return ImportManifest::query()->create(array_merge([
            'file_path' => $path,
            'checksum' => $checksum,
            'source_type' => $importer->identifier(),
            'source_name' => $importer->displayName(),
            'source_url' => $metadata['source_url'] ?? null,
            'license' => $metadata['license'] ?? $importer->licensing()['license'],
            'license_url' => $metadata['license_url'] ?? $importer->licensing()['license_url'],
            'importer' => $importer->identifier(),
            'language' => $metadata['language'] ?? $importer->supportedLanguages()[0],
            'rights_notes' => $metadata['rights_notes'] ?? $importer->licensing()['rights_notes'],
            'status' => 'running',
            'started_at' => now(),
            'total_records' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_skipped' => 0,
            'records_failed' => 0,
        ], []));
    }

    /**
     * @param  list<string>  $errors
     */
    private function finishManifest(ImportManifest $manifest, ImportResult $result, array $errors = []): void
    {
        $manifest->update([
            'status' => $result->failures === 0 ? 'completed' : 'failed',
            'finished_at' => now(),
            'total_records' => $result->total(),
            'records_created' => $result->created,
            'records_updated' => $result->updated,
            'records_skipped' => $result->skipped,
            'records_failed' => $result->failures,
            'error_message' => $errors === [] ? null : implode("\n", array_slice($errors, 0, 20)),
        ]);
    }

    private function failManifest(ImportManifest $manifest, string $message): void
    {
        $manifest->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
            'records_failed' => 1,
        ]);
    }

    private function duration(float $started): float
    {
        return round(microtime(true) - $started, 4);
    }
}
