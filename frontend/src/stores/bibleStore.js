import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchBibleContent } from '../services/bibleService';
import { createAsyncResource } from '../utils/createAsyncResource';

export const useBibleStore = defineStore('bible', () => {
  const translation = ref('RSV-CE');
  const sampleMode = ref(false);
  const copyrightNotice = ref('');

  const resource = createAsyncResource(fetchBibleContent, (data) => {
    translation.value = data.translation || 'RSV-CE';
    sampleMode.value = Boolean(data.sampleMode);
    copyrightNotice.value = data.copyrightNotice || '';

    return data.verses || [];
  });

  async function loadBibleContent(book, chapter) {
    return resource.load(book, chapter);
  }

  async function retry() {
    return resource.retry();
  }

  return {
    verses: resource.data,
    loading: resource.loading,
    refreshing: resource.refreshing,
    error: resource.error,
    hasLoaded: resource.hasLoaded,
    translation,
    sampleMode,
    copyrightNotice,
    loadBibleContent,
    retry,
  };
});
