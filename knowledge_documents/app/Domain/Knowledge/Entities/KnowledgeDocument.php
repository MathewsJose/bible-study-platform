<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Entities;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use App\Domain\Knowledge\ValueObjects\DocumentReference;

final readonly class KnowledgeDocument
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<float>|null  $embedding
     */
    public function __construct(
        public string $id,
        public SourceType $sourceType,
        public string $sourceName,
        public Tradition $tradition,
        public DocumentReference $reference,
        public string $title,
        public string $content,
        public array $metadata = [],
        public ?array $embedding = null,
    ) {}
}
