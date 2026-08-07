<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum RelationshipType: string
{
    case ScriptureReference = 'SCRIPTURE_REFERENCE';
    case CatechismReference = 'CATECHISM_REFERENCE';
    case ChurchFatherReference = 'CHURCH_FATHER_REFERENCE';
    case RelatedVerse = 'RELATED_VERSE';
    case SameTopic = 'SAME_TOPIC';
    case PartOf = 'PART_OF';
    case CommentsOn = 'COMMENTS_ON';
    case References = 'REFERENCES';
    case Fulfills = 'FULFILLS';
    case Quotes = 'QUOTES';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
