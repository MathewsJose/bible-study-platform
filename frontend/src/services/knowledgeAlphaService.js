import { apiGet, apiPost } from './api';
import { normalizeAnswerEnvelope } from './knowledgeAlphaContract';

function authHeaders(token) {
  return token
    ? {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      }
    : {
        Accept: 'application/json',
      };
}

export async function askKnowledgeQuestion({ question, token, filters }) {
  const payload = await apiPost(
    '/v1/knowledge/answer',
    {
      question,
      filters,
    },
    {
      headers: authHeaders(token),
      timeout: 30000,
    },
  );

  return normalizeAnswerEnvelope(payload);
}

export async function submitAnswerFeedback({ token, feedback }) {
  return apiPost('/v1/knowledge/answers/feedback', feedback, {
    headers: authHeaders(token),
  });
}

export async function resolveKnowledgeReference(reference) {
  return apiGet(`/v1/knowledge/reference/${encodeURIComponent(reference)}`);
}
