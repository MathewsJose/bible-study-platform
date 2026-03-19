import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchHistoricalData } from '../services/historyService';

export const useHistoryStore = defineStore('history', () => {
  const items = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function loadHistoricalData(book, chapter, verse) {
    loading.value = true;
    error.value = null;

    try {
      const data = await fetchHistoricalData(book, chapter, verse);
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
    loadHistoricalData,
  };
});