import { defineStore } from 'pinia';
import { ref, watch } from 'vue';

export const useBookmarkStore = defineStore('bookmarks', () => {
  const bookmarks = ref(JSON.parse(localStorage.getItem('bookmarks') || '[]'));

  function addBookmark(payload) {
    const exists = bookmarks.value.some(
      (item) =>
        item.book === payload.book &&
        item.chapter === payload.chapter &&
        item.verse === payload.verse
    );

    if (!exists) {
      bookmarks.value.push(payload);
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
      localStorage.setItem('bookmarks', JSON.stringify(value));
    },
    { deep: true }
  );

  return {
    bookmarks,
    addBookmark,
    removeBookmark,
  };
});