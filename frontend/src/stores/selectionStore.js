import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  DEFAULT_BOOK,
  DEFAULT_CHAPTER,
  DEFAULT_LANGUAGE,
  DEFAULT_VERSION,
  getChapterCount,
  getVersionLanguage,
  isValidBook,
  isValidChapter,
  isValidVersion,
  isValidVerseNumber,
} from '../utils/constants';

export const useSelectionStore = defineStore('selection', () => {
  const selectedBook = ref(DEFAULT_BOOK);
  const selectedChapter = ref(DEFAULT_CHAPTER);
  const selectedVerse = ref(null);
  const selectedLanguage = ref(DEFAULT_LANGUAGE);
  const selectedVersion = ref(DEFAULT_VERSION);

  const selectionKey = computed(
    () =>
      `${selectedLanguage.value}:${selectedVersion.value}:${selectedBook.value}:${selectedChapter.value}:${selectedVerse.value ?? 'all'}`
  );

  function setBook(book) {
    if (!isValidBook(book)) {
      return false;
    }

    selectedBook.value = book;
    selectedChapter.value = 1;
    selectedVerse.value = null;
    return true;
  }

  function setChapter(chapter) {
    const normalizedChapter = Number(chapter);
    if (!isValidChapter(selectedBook.value, normalizedChapter)) {
      return false;
    }

    selectedChapter.value = normalizedChapter;
    selectedVerse.value = null;
    return true;
  }

  function setVerse(verse) {
    if (verse == null) {
      selectedVerse.value = null;
      return true;
    }

    const normalizedVerse = Number(verse);
    if (!isValidVerseNumber(normalizedVerse)) {
      return false;
    }

    selectedVerse.value = normalizedVerse;
    return true;
  }

  function setVersion(version) {
    if (!isValidVersion(version)) {
      return false;
    }

    selectedVersion.value = version;
    selectedLanguage.value = getVersionLanguage(version);
    return true;
  }

  function setSelection({ book, chapter, verse = null }) {
    if (!isValidBook(book)) {
      return false;
    }

    const normalizedChapter = Number(chapter);
    if (!isValidChapter(book, normalizedChapter)) {
      return false;
    }

    if (verse != null && !isValidVerseNumber(verse)) {
      return false;
    }

    selectedBook.value = book;
    selectedChapter.value = normalizedChapter;
    selectedVerse.value = verse == null ? null : Number(verse);

    return true;
  }

  function clearVerse() {
    selectedVerse.value = null;
  }

  const chapterCount = computed(() => getChapterCount(selectedBook.value));
  const chapterOptions = computed(() =>
    Array.from({ length: chapterCount.value }, (_, index) => index + 1)
  );
  const currentReference = computed(() =>
    selectedVerse.value == null
      ? `${selectedBook.value} ${selectedChapter.value}`
      : `${selectedBook.value} ${selectedChapter.value}:${selectedVerse.value}`
  );
  const hasVerseSelected = computed(() => selectedVerse.value != null);

  return {
    selectedBook,
    selectedChapter,
    selectedVerse,
    selectedLanguage,
    selectedVersion,
    selectionKey,
    chapterCount,
    chapterOptions,
    currentReference,
    hasVerseSelected,
    setBook,
    setChapter,
    setVerse,
    setVersion,
    setSelection,
    clearVerse,
  };
});
