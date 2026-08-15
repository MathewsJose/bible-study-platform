export function sourceTypeLabel(sourceType) {
  const labels = {
    bible_verse: 'Bible',
    bible_chapter: 'Bible',
    catechism: 'Catechism',
    church_father: 'Church Father',
  };

  return labels[sourceType] || 'Source';
}

export function normalizeAnswerEnvelope(payload) {
  const data = payload?.data || payload || {};

  return {
    requestId: payload?.request_id || data.request_id || '',
    answer: data.answer || '',
    citations: Array.isArray(data.citations) ? data.citations : [],
    sources: Array.isArray(data.supporting_documents) ? data.supporting_documents : [],
    provider: data.provider || null,
    model: data.model || null,
    metadata: data.metadata || {},
    diagnostics: data.diagnostics || {},
  };
}
