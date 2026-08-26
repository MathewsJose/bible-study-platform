<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Database\Factories\RetrievalContextualDocumentRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $source_document_id
 * @property string $source_type
 * @property string $source_name
 * @property string $reference
 * @property string|null $book
 * @property int|null $chapter
 * @property int|null $verse
 * @property string $document_type
 * @property string $context_window
 * @property string $context_text
 * @property string $context_checksum
 * @property list<float>|string|null $embedding
 * @property string|null $embedding_model
 * @property string|null $embedding_provider
 * @property int|null $embedding_dimensions
 * @property \DateTimeImmutable|null $embedded_at
 * @property string|null $embedding_error
 */
final class RetrievalContextualDocumentRecord extends Model
{
    /** @use HasFactory<RetrievalContextualDocumentRecordFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'retrieval_contextual_documents';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'source_document_id',
        'source_type',
        'source_name',
        'reference',
        'book',
        'chapter',
        'verse',
        'document_type',
        'context_window',
        'context_text',
        'context_checksum',
        'embedding',
        'embedding_model',
        'embedding_provider',
        'embedding_dimensions',
        'embedded_at',
        'embedding_error',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'chapter' => 'integer',
            'verse' => 'integer',
            'embedding' => 'array',
            'embedding_dimensions' => 'integer',
            'embedded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<KnowledgeDocumentRecord, $this> */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocumentRecord::class, 'source_document_id');
    }

    /** @return Factory<RetrievalContextualDocumentRecord> */
    protected static function newFactory(): Factory
    {
        return RetrievalContextualDocumentRecordFactory::new();
    }
}
