<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum SourceInventoryStatus: string
{
    case Approved = 'approved';
    case RequiresVerification = 'requires_verification';
    case Blocked = 'blocked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
