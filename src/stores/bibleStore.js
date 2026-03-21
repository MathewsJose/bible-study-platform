import { defineStore } from 'pinia';
import { fetchBibleContent } from '../services/bibleService';
import { createAsyncResource } from '../utils/createAsyncResource';

export const useBibleStore = defineStore('bible', () => {
  const resource = createAsyncResource(fetchBibleContent, (data) => data.verses || []);

  async function loadBibleContent(book, chapter) {
    return resource.load(book, chapter);
  }

  return {
    verses: resource.data,
    loading: resource.loading,
    refreshing: resource.refreshing,
    error: resource.error,
    hasLoaded: resource.hasLoaded,
    loadBibleContent,
  };
});
