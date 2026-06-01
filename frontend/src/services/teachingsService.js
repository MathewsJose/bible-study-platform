import { apiGet } from './api';
import { getSampleTeachingsData } from './sampleData';

export async function fetchTeachingsData(book, chapter, verse, language, version) {
  try {
    return await apiGet('/teachings', { book, chapter, verse, language, version });
  } catch (error) {
    if (canUseSampleFallback(book, chapter, version)) {
      return getSampleTeachingsData(book, chapter, verse);
    }

    throw error;
  }
}

function canUseSampleFallback(book, chapter, version) {
  return book === 'John' && [1, 3].includes(Number(chapter)) && (!version || version === 'cpdv');
}
