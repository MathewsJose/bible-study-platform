import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { DEFAULT_BOOK, DEFAULT_CHAPTER } from '../utils/constants';

export const useSelectionStore = defineStore('selection', () => {
  const selectedBook = ref(DEFAULT_BOOK);
  const selectedChapter = ref(DEFAULT_CHAPTER);
  const selectedVerse = ref(null);

  const selectionKey = computed(() => ({
    book: selectedBook.value,
    chapter: selectedChapter.value,
    verse: selectedVerse.value,
  }));

  function setBook(book) {
    selectedBook.value = book;
    selectedChapter.value = 1;
    selectedVerse.value = null;
  }

  function setChapter(chapter) {
    selectedChapter.value = Number(chapter);
    selectedVerse.value = null;
  }

  function setVerse(verse) {
    selectedVerse.value = verse;
  }

  return {
    selectedBook,
    selectedChapter,
    selectedVerse,
    selectionKey,
    setBook,
    setChapter,
    setVerse,
  };
});