import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';
import {
  askKnowledgeQuestion,
  resolveKnowledgeReference,
  submitAnswerFeedback,
} from '../services/knowledgeAlphaService';

const TOKEN_KEY = 'alpha-api-token';

function readToken() {
  try {
    return globalThis.localStorage?.getItem(TOKEN_KEY) || '';
  } catch {
    return '';
  }
}

export const useAlphaAskStore = defineStore('alphaAsk', () => {
  const token = ref(readToken());
  const question = ref('What does the Catholic Church teach about the Incarnation?');
  const answer = ref(null);
  const loading = ref(false);
  const feedbackLoading = ref(false);
  const resolvingReference = ref(null);
  const resolvedSource = ref(null);
  const error = ref('');
  const feedbackMessage = ref('');
  const selectedReason = ref('');
  const comment = ref('');

  const canAsk = computed(() => question.value.trim().length >= 2 && token.value.trim().length > 0);
  const hasSources = computed(() => Array.isArray(answer.value?.sources) && answer.value.sources.length > 0);

  async function ask() {
    if (!canAsk.value) {
      error.value = token.value.trim() ? 'Enter a question before asking.' : 'Paste your private alpha token before asking.';
      return;
    }

    loading.value = true;
    error.value = '';
    feedbackMessage.value = '';
    resolvedSource.value = null;

    try {
      answer.value = await askKnowledgeQuestion({
        question: question.value.trim(),
        token: token.value.trim(),
        filters: {
          source_types: ['bible_verse', 'catechism', 'church_father'],
          tradition: 'catholic',
        },
      });
    } catch (apiError) {
      answer.value = null;
      error.value = apiError.message || 'Sorry, I could not generate an answer right now. Please try again.';
    } finally {
      loading.value = false;
    }
  }

  async function sendFeedback(rating) {
    if (!answer.value?.requestId || !token.value.trim()) {
      feedbackMessage.value = 'Feedback needs an answer request first.';
      return;
    }

    feedbackLoading.value = true;
    feedbackMessage.value = '';

    try {
      await submitAnswerFeedback({
        token: token.value.trim(),
        feedback: {
          request_id: answer.value.requestId,
          rating,
          reason: rating === 'not_helpful' ? selectedReason.value || 'other' : null,
          comment: rating === 'not_helpful' ? comment.value : null,
          provider: answer.value.provider,
          model: answer.value.model,
          retrieval_strategy: answer.value.metadata?.retrieval_profile || null,
          source_count: answer.value.sources.length,
          citation_count: answer.value.citations.length,
          client_surface: 'alpha_question',
          answer_status: answer.value.answer ? 'answered' : 'empty',
          latency_ms: answer.value.diagnostics?.timings?.total || null,
        },
      });
      feedbackMessage.value = 'Feedback saved.';
    } catch (apiError) {
      feedbackMessage.value = apiError.message || 'Feedback could not be saved.';
    } finally {
      feedbackLoading.value = false;
    }
  }

  async function openReference(reference) {
    if (!reference) {
      return;
    }

    resolvingReference.value = reference;
    resolvedSource.value = null;

    try {
      resolvedSource.value = await resolveKnowledgeReference(reference);
    } catch (apiError) {
      resolvedSource.value = {
        error: apiError.message || 'Source could not be opened.',
      };
    } finally {
      resolvingReference.value = null;
    }
  }

  watch(token, (nextToken) => {
    try {
      globalThis.localStorage?.setItem(TOKEN_KEY, nextToken);
    } catch {
      // Browser storage is optional for private alpha testing.
    }
  });

  return {
    token,
    question,
    answer,
    loading,
    feedbackLoading,
    resolvingReference,
    resolvedSource,
    error,
    feedbackMessage,
    selectedReason,
    comment,
    canAsk,
    hasSources,
    ask,
    sendFeedback,
    openReference,
  };
});
