import { apiGet, getApiBaseUrl } from './api';
import { getSampleBibleContent } from './sampleData';

export async function fetchBibleContent(book, chapter, language, version) {
  try {
    return await apiGet('/bible', { book, chapter, language, version });
  } catch (error) {
    if (canUseSampleFallback(book, chapter, version)) {
      return getSampleBibleContent(book, chapter, version);
    }

    throw error;
  }
}

function canUseSampleFallback(book, chapter, version) {
  return (
    book === 'John' &&
    [1, 3].includes(Number(chapter)) &&
    ['cpdv', 'drb', 'nrsvce'].includes(version ?? 'cpdv')
  );
}
