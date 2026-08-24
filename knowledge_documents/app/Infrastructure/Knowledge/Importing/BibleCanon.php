<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Importing;

use Illuminate\Support\Str;

final class BibleCanon
{
    /**
     * @var array<string, array{order: int, abbreviation: string, testament: string}>
     */
    private const BOOKS = [
        'genesis' => ['order' => 1, 'abbreviation' => 'Gen', 'testament' => 'Old Testament'],
        'exodus' => ['order' => 2, 'abbreviation' => 'Exod', 'testament' => 'Old Testament'],
        'leviticus' => ['order' => 3, 'abbreviation' => 'Lev', 'testament' => 'Old Testament'],
        'numbers' => ['order' => 4, 'abbreviation' => 'Num', 'testament' => 'Old Testament'],
        'deuteronomy' => ['order' => 5, 'abbreviation' => 'Deut', 'testament' => 'Old Testament'],
        'joshua' => ['order' => 6, 'abbreviation' => 'Josh', 'testament' => 'Old Testament'],
        'judges' => ['order' => 7, 'abbreviation' => 'Judg', 'testament' => 'Old Testament'],
        'ruth' => ['order' => 8, 'abbreviation' => 'Ruth', 'testament' => 'Old Testament'],
        '1 samuel' => ['order' => 9, 'abbreviation' => '1 Sam', 'testament' => 'Old Testament'],
        '2 samuel' => ['order' => 10, 'abbreviation' => '2 Sam', 'testament' => 'Old Testament'],
        '1 kings' => ['order' => 11, 'abbreviation' => '1 Kgs', 'testament' => 'Old Testament'],
        '2 kings' => ['order' => 12, 'abbreviation' => '2 Kgs', 'testament' => 'Old Testament'],
        '1 chronicles' => ['order' => 13, 'abbreviation' => '1 Chr', 'testament' => 'Old Testament'],
        '2 chronicles' => ['order' => 14, 'abbreviation' => '2 Chr', 'testament' => 'Old Testament'],
        'ezra' => ['order' => 15, 'abbreviation' => 'Ezra', 'testament' => 'Old Testament'],
        'nehemiah' => ['order' => 16, 'abbreviation' => 'Neh', 'testament' => 'Old Testament'],
        'tobit' => ['order' => 17, 'abbreviation' => 'Tob', 'testament' => 'Old Testament'],
        'judith' => ['order' => 18, 'abbreviation' => 'Jdt', 'testament' => 'Old Testament'],
        'esther' => ['order' => 19, 'abbreviation' => 'Esth', 'testament' => 'Old Testament'],
        '1 maccabees' => ['order' => 20, 'abbreviation' => '1 Macc', 'testament' => 'Old Testament'],
        '2 maccabees' => ['order' => 21, 'abbreviation' => '2 Macc', 'testament' => 'Old Testament'],
        'job' => ['order' => 22, 'abbreviation' => 'Job', 'testament' => 'Old Testament'],
        'psalms' => ['order' => 23, 'abbreviation' => 'Ps', 'testament' => 'Old Testament'],
        'proverbs' => ['order' => 24, 'abbreviation' => 'Prov', 'testament' => 'Old Testament'],
        'ecclesiastes' => ['order' => 25, 'abbreviation' => 'Eccl', 'testament' => 'Old Testament'],
        'song of songs' => ['order' => 26, 'abbreviation' => 'Song', 'testament' => 'Old Testament'],
        'wisdom' => ['order' => 27, 'abbreviation' => 'Wis', 'testament' => 'Old Testament'],
        'sirach' => ['order' => 28, 'abbreviation' => 'Sir', 'testament' => 'Old Testament'],
        'isaiah' => ['order' => 29, 'abbreviation' => 'Isa', 'testament' => 'Old Testament'],
        'jeremiah' => ['order' => 30, 'abbreviation' => 'Jer', 'testament' => 'Old Testament'],
        'lamentations' => ['order' => 31, 'abbreviation' => 'Lam', 'testament' => 'Old Testament'],
        'baruch' => ['order' => 32, 'abbreviation' => 'Bar', 'testament' => 'Old Testament'],
        'ezekiel' => ['order' => 33, 'abbreviation' => 'Ezek', 'testament' => 'Old Testament'],
        'daniel' => ['order' => 34, 'abbreviation' => 'Dan', 'testament' => 'Old Testament'],
        'hosea' => ['order' => 35, 'abbreviation' => 'Hos', 'testament' => 'Old Testament'],
        'joel' => ['order' => 36, 'abbreviation' => 'Joel', 'testament' => 'Old Testament'],
        'amos' => ['order' => 37, 'abbreviation' => 'Amos', 'testament' => 'Old Testament'],
        'obadiah' => ['order' => 38, 'abbreviation' => 'Obad', 'testament' => 'Old Testament'],
        'jonah' => ['order' => 39, 'abbreviation' => 'Jonah', 'testament' => 'Old Testament'],
        'micah' => ['order' => 40, 'abbreviation' => 'Mic', 'testament' => 'Old Testament'],
        'nahum' => ['order' => 41, 'abbreviation' => 'Nah', 'testament' => 'Old Testament'],
        'habakkuk' => ['order' => 42, 'abbreviation' => 'Hab', 'testament' => 'Old Testament'],
        'zephaniah' => ['order' => 43, 'abbreviation' => 'Zeph', 'testament' => 'Old Testament'],
        'haggai' => ['order' => 44, 'abbreviation' => 'Hag', 'testament' => 'Old Testament'],
        'zechariah' => ['order' => 45, 'abbreviation' => 'Zech', 'testament' => 'Old Testament'],
        'malachi' => ['order' => 46, 'abbreviation' => 'Mal', 'testament' => 'Old Testament'],
        'matthew' => ['order' => 47, 'abbreviation' => 'Matt', 'testament' => 'New Testament'],
        'mark' => ['order' => 48, 'abbreviation' => 'Mark', 'testament' => 'New Testament'],
        'luke' => ['order' => 49, 'abbreviation' => 'Luke', 'testament' => 'New Testament'],
        'john' => ['order' => 50, 'abbreviation' => 'John', 'testament' => 'New Testament'],
        'acts' => ['order' => 51, 'abbreviation' => 'Acts', 'testament' => 'New Testament'],
        'romans' => ['order' => 52, 'abbreviation' => 'Rom', 'testament' => 'New Testament'],
        '1 corinthians' => ['order' => 53, 'abbreviation' => '1 Cor', 'testament' => 'New Testament'],
        '2 corinthians' => ['order' => 54, 'abbreviation' => '2 Cor', 'testament' => 'New Testament'],
        'galatians' => ['order' => 55, 'abbreviation' => 'Gal', 'testament' => 'New Testament'],
        'ephesians' => ['order' => 56, 'abbreviation' => 'Eph', 'testament' => 'New Testament'],
        'philippians' => ['order' => 57, 'abbreviation' => 'Phil', 'testament' => 'New Testament'],
        'colossians' => ['order' => 58, 'abbreviation' => 'Col', 'testament' => 'New Testament'],
        '1 thessalonians' => ['order' => 59, 'abbreviation' => '1 Thess', 'testament' => 'New Testament'],
        '2 thessalonians' => ['order' => 60, 'abbreviation' => '2 Thess', 'testament' => 'New Testament'],
        '1 timothy' => ['order' => 61, 'abbreviation' => '1 Tim', 'testament' => 'New Testament'],
        '2 timothy' => ['order' => 62, 'abbreviation' => '2 Tim', 'testament' => 'New Testament'],
        'titus' => ['order' => 63, 'abbreviation' => 'Titus', 'testament' => 'New Testament'],
        'philemon' => ['order' => 64, 'abbreviation' => 'Phlm', 'testament' => 'New Testament'],
        'hebrews' => ['order' => 65, 'abbreviation' => 'Heb', 'testament' => 'New Testament'],
        'james' => ['order' => 66, 'abbreviation' => 'Jas', 'testament' => 'New Testament'],
        '1 peter' => ['order' => 67, 'abbreviation' => '1 Pet', 'testament' => 'New Testament'],
        '2 peter' => ['order' => 68, 'abbreviation' => '2 Pet', 'testament' => 'New Testament'],
        '1 john' => ['order' => 69, 'abbreviation' => '1 John', 'testament' => 'New Testament'],
        '2 john' => ['order' => 70, 'abbreviation' => '2 John', 'testament' => 'New Testament'],
        '3 john' => ['order' => 71, 'abbreviation' => '3 John', 'testament' => 'New Testament'],
        'jude' => ['order' => 72, 'abbreviation' => 'Jude', 'testament' => 'New Testament'],
        'revelation' => ['order' => 73, 'abbreviation' => 'Rev', 'testament' => 'New Testament'],
    ];

    public function isValidBook(string $book): bool
    {
        return isset(self::BOOKS[$this->key($book)]);
    }

    public function canonicalBook(string $book): string
    {
        $key = $this->key($book);

        return Str::of($key)->headline()->replace(' Of ', ' of ')->toString();
    }

    public function abbreviation(string $book, ?string $fallback = null): string
    {
        return $fallback ?: self::BOOKS[$this->key($book)]['abbreviation'];
    }

    public function testament(string $book, ?string $fallback = null): string
    {
        return $fallback ?: self::BOOKS[$this->key($book)]['testament'];
    }

    public function bookOrder(string $book): int
    {
        return self::BOOKS[$this->key($book)]['order'];
    }

    public function canonicalOrder(string $book, int $chapter, int $verse = 0): int
    {
        return ($this->bookOrder($book) * 1_000_000) + ($chapter * 1_000) + $verse;
    }

    /**
     * @return list<string>
     */
    public function books(): array
    {
        return array_map(
            static fn (string $book): string => Str::of($book)->headline()->replace(' Of ', ' of ')->toString(),
            array_keys(self::BOOKS),
        );
    }

    /**
     * @return list<string>
     */
    public function deuterocanonicalBooks(): array
    {
        return ['Tobit', 'Judith', 'Wisdom', 'Sirach', 'Baruch', '1 Maccabees', '2 Maccabees'];
    }

    private function key(string $book): string
    {
        return Str::of($book)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }
}
