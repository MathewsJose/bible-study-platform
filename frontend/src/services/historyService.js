import { apiGet, getApiBaseUrl } from './api';
import { getSampleHistoricalData } from './sampleData';

export async function fetchHistoricalData(book, chapter, verse, language, version) {
  if (!getApiBaseUrl()) {
    return getSampleHistoricalData(book, chapter, verse);
  }

  try {
    return await apiGet('/history', { book, chapter, verse, language, version });
  } catch (error) {
    if (canUseSampleFallback(book, chapter, version)) {
      return getSampleHistoricalData(book, chapter, verse);
    }

    throw error;
  }
}

function canUseSampleFallback(book, chapter, version) {
  return book === 'John' && [1, 3].includes(Number(chapter)) && (!version || version === 'cpdv');
}
