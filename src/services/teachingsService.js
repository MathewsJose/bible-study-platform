import api from './api';

export async function fetchTeachingsData(book, chapter, verse) {
  const { data } = await api.get('/teachings', {
    params: { book, chapter, verse },
  });
  return data;
}