<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $source_document_id
 * @property string $target_document_id
 * @property string $relationship_type
 * @property float $confidence
 * @property array<string, mixed> $provenance
 * @property array<string, mixed> $metadata
 * @property-read KnowledgeDocumentRecord|null $sourceDocument
 * @property-read KnowledgeDocumentRecord|null $targetDocument
 */
final class KnowledgeDocumentRelationshipRecord extends Model
{
    use HasUuids;

    protected $table = 'knowledge_document_relationships';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'source_document_id',
        'target_document_id',
        'relationship_type',
        'confidence',
        'provenance',
        'metadata',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'confidence' => 1.0,
        'provenance' => '{}',
        'metadata' => '{}',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'provenance' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<KnowledgeDocumentRecord, $this> */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocumentRecord::class, 'source_document_id');
    }

    /** @return BelongsTo<KnowledgeDocumentRecord, $this> */
    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocumentRecord::class, 'target_document_id');
    }
}
