import api from './api';

export async function fetchBibleContent(book, chapter) {
  const { data } = await api.get('/bible', {
    params: { book, chapter },
  });
  return data;
}