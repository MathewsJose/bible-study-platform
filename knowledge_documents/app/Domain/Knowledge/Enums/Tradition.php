<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum Tradition: string
{
    case Catholic = 'catholic';
    case Latin = 'latin';
    case EasternCatholic = 'eastern_catholic';
    case ByzantineCatholic = 'byzantine_catholic';
    case Maronite = 'maronite';
    case Patristic = 'patristic';
    case EcumenicalCouncil = 'ecumenical_council';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
