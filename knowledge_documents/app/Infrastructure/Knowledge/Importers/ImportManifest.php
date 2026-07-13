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
        'file_hash',
        'file_type',
        'source_name',
        'total_records',
        'imported_records',
        'skipped_records',
        'failed_records',
        'imported_at',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'imported_records' => 'integer',
        'skipped_records' => 'integer',
        'failed_records' => 'integer',
        'imported_at' => 'datetime',
    ];
}
