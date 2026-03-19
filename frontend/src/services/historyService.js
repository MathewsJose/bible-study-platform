import api from './api';

export async function fetchHistoricalData(book, chapter, verse) {
  const { data } = await api.get('/history', {
    params: { book, chapter, verse },
  });
  return data;
}