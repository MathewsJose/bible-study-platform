const STORAGE_KEY = 'bookmarks';

function isBookmarkShape(value) {
  return (
    value &&
    typeof value === 'object' &&
    typeof value.book === 'string' &&
    Number.isInteger(Number(value.chapter)) &&
    Number.isInteger(Number(value.verse))
  );
}

export function parseBookmarks(rawValue) {
  if (!rawValue) {
    return [];
  }

  try {
    const parsed = JSON.parse(rawValue);
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.filter(isBookmarkShape).map((bookmark) => ({
      book: bookmark.book,
      chapter: Number(bookmark.chapter),
      verse: Number(bookmark.verse),
    }));
  } catch {
    return [];
  }
}

export function loadBookmarks(storage = globalThis.localStorage) {
  if (!storage?.getItem) {
    return [];
  }

  return parseBookmarks(storage.getItem(STORAGE_KEY));
}

export function saveBookmarks(bookmarks, storage = globalThis.localStorage) {
  if (!storage?.setItem) {
    return;
  }

  storage.setItem(STORAGE_KEY, JSON.stringify(bookmarks));
}
