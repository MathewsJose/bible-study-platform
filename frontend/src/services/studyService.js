import { apiGet, getApiBaseUrl } from './api';
import { getSampleBibleContent, getSampleHistoricalData, getSampleTeachingsData } from './sampleData';

export async function fetchStudyPayload(book, chapter, verse, language, version) {
  if (!getApiBaseUrl()) {
    return getSampleStudyPayload(book, chapter, verse);
  }

  return await apiGet('/study', { book, chapter, verse, language, version });
}

function getSampleStudyPayload(book, chapter, verse) {
  return {
    bible: getSampleBibleContent(book, chapter),
    history: getSampleHistoricalData(book, chapter, verse),
    teachings: getSampleTeachingsData(book, chapter, verse),
  };
}
