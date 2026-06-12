<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Enums;

enum SourceType: string
{
    case BibleVerse = 'bible_verse';
    case BibleChapter = 'bible_chapter';
    case Catechism = 'catechism';
    case ChurchFather = 'church_father';
    case PapalDocument = 'papal_document';
    case CouncilDocument = 'council_document';
    case Commentary = 'commentary';
    case Article = 'article';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
