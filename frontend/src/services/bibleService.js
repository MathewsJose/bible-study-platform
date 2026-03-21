import { apiGet, getApiBaseUrl } from './api';
import { getSampleBibleContent } from './sampleData';

export async function fetchBibleContent(book, chapter) {
  if (!getApiBaseUrl()) {
    return getSampleBibleContent(book, chapter);
  }

  try {
    return await apiGet('/bible', { book, chapter });
  } catch (error) {
    if (book === 'John' && Number(chapter) === 1) {
      return getSampleBibleContent(book, chapter);
    }

    throw error;
  }
}
