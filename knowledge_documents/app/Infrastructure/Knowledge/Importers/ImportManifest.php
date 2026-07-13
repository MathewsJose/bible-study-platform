<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Domain\Knowledge\ValueObjects\SourceMetadata;
use Illuminate\Database\Eloquent\Model;

final class ImportManifest extends Model
{
    protected $table = 'import_manifests';

    public $incrementing = true;

    protected $fillable = [
        'file_path',
        'checksum',
        'source_type',
        'source_name',
        'source_url',
        'license',
        'license_url',
        'importer',
        'language',
        'rights_notes',
        'status',
        'total_records',
        'records_created',
        'records_updated',
        'records_skipped',
        'records_failed',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'records_created' => 'integer',
        'records_updated' => 'integer',
        'records_skipped' => 'integer',
        'records_failed' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function toSourceMetadata(): SourceMetadata
    {
        return new SourceMetadata(
            sourceUrl: $this->source_url,
            license: $this->license,
            licenseUrl: $this->license_url,
            importedFrom: $this->importer,
            importedAt: $this->started_at?->toIso8601String(),
            rightsNotes: $this->rights_notes,
            language: $this->language ?? 'en',
        );
    }
}
