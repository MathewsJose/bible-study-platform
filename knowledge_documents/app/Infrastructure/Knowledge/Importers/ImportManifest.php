<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

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
}
