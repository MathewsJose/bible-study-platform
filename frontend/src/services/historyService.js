import { apiGet, getApiBaseUrl } from './api';
import { getSampleHistoricalData } from './sampleData';

export async function fetchHistoricalData(book, chapter, verse) {
  if (!getApiBaseUrl()) {
    return getSampleHistoricalData(book, chapter, verse);
  }

  try {
    return await apiGet('/history', { book, chapter, verse });
  } catch (error) {
    if (book === 'John' && Number(chapter) === 1) {
      return getSampleHistoricalData(book, chapter, verse);
    }

    throw error;
  }
}
