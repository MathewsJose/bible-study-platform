import { defineStore } from 'pinia';
import { fetchTeachingsData } from '../services/teachingsService';
import { createAsyncResource } from '../utils/createAsyncResource';

export const useTeachingsStore = defineStore('teachings', () => {
  const resource = createAsyncResource(fetchTeachingsData, (data) => data.items || []);

  async function loadTeachingsData(book, chapter, verse) {
    return resource.load(book, chapter, verse);
  }

  return {
    items: resource.data,
    loading: resource.loading,
    refreshing: resource.refreshing,
    error: resource.error,
    hasLoaded: resource.hasLoaded,
    loadTeachingsData,
  };
});
