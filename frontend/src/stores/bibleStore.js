import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchBibleContent } from '../services/bibleService';
import { createAsyncResource } from '../utils/createAsyncResource';

export const useBibleStore = defineStore('bible', () => {
  const translation = ref('CPDV');
  const sampleMode = ref(false);
  const copyrightNotice = ref('');

  const resource = createAsyncResource(fetchBibleContent, (data) => {
    translation.value = data.translation || data.version?.toUpperCase() || 'CPDV';
    sampleMode.value = Boolean(data.sampleMode);
    copyrightNotice.value = data.copyrightNotice || '';

    return data.verses || [];
  });

  async function loadBibleContent(book, chapter, language, version) {
    return resource.load(book, chapter, language, version);
  }

  async function retry() {
    return resource.retry();
  }

  function hydrateBibleContent(data, book, chapter, language, version) {
    return resource.hydrate(data, book, chapter, language, version);
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
    hydrateBibleContent,
    retry,
  };
});
