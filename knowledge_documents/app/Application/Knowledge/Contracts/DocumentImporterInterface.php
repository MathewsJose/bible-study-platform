<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\ImportResult;
use App\Domain\Knowledge\ValueObjects\SourceMetadata;

interface DocumentImporterInterface
{
    /**
     * @param  iterable<array<string, mixed>>  $records
     */
    public function import(iterable $records, ?SourceMetadata $sourceMetadata = null): ImportResult;
}
