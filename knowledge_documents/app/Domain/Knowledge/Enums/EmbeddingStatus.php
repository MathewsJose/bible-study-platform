<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum EmbeddingStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
