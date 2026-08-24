<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum CopyrightStatus: string
{
    case Verified = 'verified';
    case PublicDomain = 'public_domain';
    case Licensed = 'licensed';
    case PermissionRequired = 'permission_required';
    case RequiresVerification = 'requires_verification';
    case Restricted = 'restricted';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function importable(): bool
    {
        return in_array($this, [self::Verified, self::PublicDomain, self::Licensed], true);
    }
}
