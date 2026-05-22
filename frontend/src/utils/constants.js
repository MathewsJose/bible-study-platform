const BOOK_DEFINITIONS = [
  ['Genesis', 50],
  ['Exodus', 40],
  ['Leviticus', 27],
  ['Numbers', 36],
  ['Deuteronomy', 34],
  ['Joshua', 24],
  ['Judges', 21],
  ['Ruth', 4],
  ['1 Samuel', 31],
  ['2 Samuel', 24],
  ['1 Kings', 22],
  ['2 Kings', 25],
  ['1 Chronicles', 29],
  ['2 Chronicles', 36],
  ['Ezra', 10],
  ['Nehemiah', 13],
  ['Tobit', 14],
  ['Judith', 16],
  ['Esther', 10],
  ['Job', 42],
  ['Psalms', 150],
  ['Proverbs', 31],
  ['Ecclesiastes', 12],
  ['Song of Songs', 8],
  ['Wisdom', 19],
  ['Sirach', 51],
  ['Isaiah', 66],
  ['Jeremiah', 52],
  ['Lamentations', 5],
  ['Baruch', 6],
  ['Ezekiel', 48],
  ['Daniel', 14],
  ['Hosea', 14],
  ['Joel', 3],
  ['Amos', 9],
  ['Obadiah', 1],
  ['Jonah', 4],
  ['Micah', 7],
  ['Nahum', 3],
  ['Habakkuk', 3],
  ['Zephaniah', 3],
  ['Haggai', 2],
  ['Zechariah', 14],
  ['Malachi', 4],
  ['1 Maccabees', 16],
  ['2 Maccabees', 15],
  ['Matthew', 28],
  ['Mark', 16],
  ['Luke', 24],
  ['John', 21],
  ['Acts', 28],
  ['Romans', 16],
  ['1 Corinthians', 16],
  ['2 Corinthians', 13],
  ['Galatians', 6],
  ['Ephesians', 6],
  ['Philippians', 4],
  ['Colossians', 4],
  ['1 Thessalonians', 5],
  ['2 Thessalonians', 3],
  ['1 Timothy', 6],
  ['2 Timothy', 4],
  ['Titus', 3],
  ['Philemon', 1],
  ['Hebrews', 13],
  ['James', 5],
  ['1 Peter', 5],
  ['2 Peter', 3],
  ['1 John', 5],
  ['2 John', 1],
  ['3 John', 1],
  ['Jude', 1],
  ['Revelation', 22],
];

export const BOOK_METADATA = BOOK_DEFINITIONS.map(([name, chapters]) => ({
  name,
  chapters,
}));

export const BIBLE_BOOKS = BOOK_METADATA.map(({ name }) => name);
export const DEFAULT_BOOK = 'John';
export const DEFAULT_CHAPTER = 3;
export const DEFAULT_LANGUAGE = 'en';
export const DEFAULT_VERSION = 'cpdv';
export const BIBLE_VERSION_OPTIONS = [
  {
    label: 'Catholic Public Domain Version',
    value: 'cpdv',
    language: 'en',
  },
  {
    label: 'Douay-Rheims',
    value: 'drb',
    language: 'en',
  },
  {
    label: 'NRSV-CE',
    value: 'nrsvce',
    language: 'en',
  },
];

const CHAPTERS_BY_BOOK = new Map(
  BOOK_METADATA.map(({ name, chapters }) => [name, chapters])
);

export function getChapterCount(book) {
  return CHAPTERS_BY_BOOK.get(book) ?? 1;
}

export function getChapterOptions(book) {
  return Array.from({ length: getChapterCount(book) }, (_, index) => index + 1);
}

export function isValidBook(book) {
  return CHAPTERS_BY_BOOK.has(book);
}

export function isValidChapter(book, chapter) {
  const chapterNumber = Number(chapter);
  return (
    Number.isInteger(chapterNumber) &&
    chapterNumber >= 1 &&
    chapterNumber <= getChapterCount(book)
  );
}

export function isValidVerseNumber(verse) {
  const verseNumber = Number(verse);
  return Number.isInteger(verseNumber) && verseNumber >= 1;
}

export function isValidVersion(version) {
  return BIBLE_VERSION_OPTIONS.some((option) => option.value === version);
}

export function getVersionLanguage(version) {
  return BIBLE_VERSION_OPTIONS.find((option) => option.value === version)?.language ?? DEFAULT_LANGUAGE;
}
