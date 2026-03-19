import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchTeachingsData } from '../services/teachingsService';

export const useTeachingsStore = defineStore('teachings', () => {
  const items = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function loadTeachingsData(book, chapter, verse) {
    loading.value = true;
    error.value = null;

    try {
      const data = await fetchTeachingsData(book, chapter, verse);
      items.value = data.items || [];
    } catch (err) {
      error.value = err.message;
      items.value = [];
    } finally {
      loading.value = false;
    }
  }

  return {
    items,
    loading,
    error,
    loadTeachingsData,
  };
});