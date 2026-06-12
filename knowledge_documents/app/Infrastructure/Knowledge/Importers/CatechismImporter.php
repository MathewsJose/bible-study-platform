<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importers;

use App\Domain\Knowledge\Enums\SourceType;

final class CatechismImporter extends AbstractDocumentImporter
{
    protected function sourceType(): SourceType
    {
        return SourceType::Catechism;
    }
}
