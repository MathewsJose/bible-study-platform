import { defineStore } from 'pinia';
import { fetchHistoricalData } from '../services/historyService';
import { createAsyncResource } from '../utils/createAsyncResource';

export const useHistoryStore = defineStore('history', () => {
  const resource = createAsyncResource(fetchHistoricalData, (data) => data.items || []);

  async function loadHistoricalData(book, chapter, verse) {
    return resource.load(book, chapter, verse);
  }

  async function retry() {
    return resource.retry();
  }

  return {
    items: resource.data,
    loading: resource.loading,
    refreshing: resource.refreshing,
    error: resource.error,
    hasLoaded: resource.hasLoaded,
    loadHistoricalData,
    retry,
  };
});
