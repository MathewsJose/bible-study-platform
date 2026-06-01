import { apiGet } from './api';
import { getSampleBibleContent, getSampleHistoricalData, getSampleTeachingsData } from './sampleData';

export async function fetchStudyPayload(book, chapter, verse, language, version) {
  try {
    return await apiGet('/study', { book, chapter, verse, language, version });
  } catch (error) {
    if (canUseSampleFallback(book, chapter, version)) {
      return getSampleStudyPayload(book, chapter, verse, version);
    }

    throw error;
  }
}

function getSampleStudyPayload(book, chapter, verse, version) {
  return {
    bible: getSampleBibleContent(book, chapter, version),
    history: getSampleHistoricalData(book, chapter, verse),
    teachings: getSampleTeachingsData(book, chapter, verse),
  };
}

function canUseSampleFallback(book, chapter, version) {
  return book === 'John' && [1, 3].includes(Number(chapter)) && ['cpdv', 'drb', 'nrsvce'].includes(version ?? 'cpdv');
}
