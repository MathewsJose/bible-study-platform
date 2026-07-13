<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum ImportStatus: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
}
