import { apiGet, getApiBaseUrl } from './api';
import { getSampleTeachingsData } from './sampleData';

export async function fetchTeachingsData(book, chapter, verse) {
  if (!getApiBaseUrl()) {
    return getSampleTeachingsData(book, chapter, verse);
  }

  try {
    return await apiGet('/teachings', { book, chapter, verse });
  } catch (error) {
    if (book === 'John' && Number(chapter) === 1) {
      return getSampleTeachingsData(book, chapter, verse);
    }

    throw error;
  }
}
