import { defineStore } from 'pinia';
import { ref, watch } from 'vue';
import { loadBookmarks, saveBookmarks } from '../utils/bookmarkStorage';

export const useBookmarkStore = defineStore('bookmarks', () => {
  const bookmarks = ref(loadBookmarks());

  function addBookmark(payload) {
    const exists = bookmarks.value.some(
      (item) =>
        item.book === payload.book &&
        item.chapter === payload.chapter &&
        item.verse === payload.verse
    );

    if (!exists) {
      bookmarks.value.push({
        book: payload.book,
        chapter: Number(payload.chapter),
        verse: Number(payload.verse),
      });
    }
  }

  function removeBookmark(payload) {
    bookmarks.value = bookmarks.value.filter(
      (item) =>
        !(
          item.book === payload.book &&
          item.chapter === payload.chapter &&
          item.verse === payload.verse
        )
    );
  }

  watch(
    bookmarks,
    (value) => {
      saveBookmarks(value);
    },
    { deep: true }
  );

  function isBookmarked(payload) {
    return bookmarks.value.some(
      (item) =>
        item.book === payload.book &&
        item.chapter === payload.chapter &&
        item.verse === payload.verse
    );
  }

  function toggleBookmark(payload) {
    if (isBookmarked(payload)) {
      removeBookmark(payload);
      return false;
    }

    addBookmark(payload);
    return true;
  }

  return {
    bookmarks,
    addBookmark,
    removeBookmark,
    isBookmarked,
    toggleBookmark,
  };
});
