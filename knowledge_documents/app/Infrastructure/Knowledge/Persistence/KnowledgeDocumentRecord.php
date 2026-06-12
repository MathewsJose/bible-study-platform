<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Database\Factories\KnowledgeDocumentRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $source_type
 * @property string $source_name
 * @property string $tradition
 * @property string $reference
 * @property string $title
 * @property string $content
 * @property array<string, mixed> $metadata
 * @property list<float>|string|null $embedding
 */
final class KnowledgeDocumentRecord extends Model
{
    /** @use HasFactory<KnowledgeDocumentRecordFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'knowledge_documents';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'source_type',
        'source_name',
        'tradition',
        'reference',
        'title',
        'content',
        'metadata',
        'embedding',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /** @return Factory<KnowledgeDocumentRecord> */
    protected static function newFactory(): Factory
    {
        return KnowledgeDocumentRecordFactory::new();
    }
}
