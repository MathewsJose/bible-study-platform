import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchBibleContent } from '../services/bibleService';

export const useBibleStore = defineStore('bible', () => {
  const verses = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function loadBibleContent(book, chapter) {
    loading.value = true;
    error.value = null;

    try {
      const data = await fetchBibleContent(book, chapter);
      verses.value = data.verses || [];
    } catch (err) {
      error.value = err.message;
      verses.value = [];
    } finally {
      loading.value = false;
    }
  }

  return {
    verses,
    loading,
    error,
    loadBibleContent,
  };
});