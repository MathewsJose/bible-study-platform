<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Application\Knowledge\Contracts\DocumentImporterInterface;
use App\Application\Knowledge\Services\KnowledgeDocumentService;
use App\Domain\Knowledge\Enums\SourceType;

abstract class AbstractDocumentImporter implements DocumentImporterInterface
{
    public function __construct(private readonly KnowledgeDocumentService $documents) {}

    abstract protected function sourceType(): SourceType;

    public function import(iterable $records): int
    {
        $count = 0;

        foreach ($records as $record) {
            $record['source_type'] = $this->sourceType()->value;
            $this->documents->create($record);
            $count++;
        }

        return $count;
    }
}
