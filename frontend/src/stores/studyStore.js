import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';

const NOTES_KEY = 'study-notes';
const HIGHLIGHTS_KEY = 'study-highlights';

function loadMap(storageKey) {
  try {
    const raw = globalThis.localStorage?.getItem(storageKey);
    if (!raw) {
      return {};
    }

    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch {
    return {};
  }
}

export const useStudyStore = defineStore('study', () => {
  const notes = ref(loadMap(NOTES_KEY));
  const highlights = ref(loadMap(HIGHLIGHTS_KEY));

  function getReferenceKey(reference) {
    return `${reference.book}:${reference.chapter}:${reference.verse}`;
  }

  function getNote(reference) {
    return notes.value[getReferenceKey(reference)] ?? '';
  }

  function saveNote(reference, content) {
    notes.value = {
      ...notes.value,
      [getReferenceKey(reference)]: content,
    };
  }

  function toggleHighlight(reference, tone = 'gold') {
    const key = getReferenceKey(reference);
    const nextHighlights = { ...highlights.value };

    if (nextHighlights[key]) {
      delete nextHighlights[key];
    } else {
      nextHighlights[key] = tone;
    }

    highlights.value = nextHighlights;
    return Boolean(nextHighlights[key]);
  }

  function getHighlight(reference) {
    return highlights.value[getReferenceKey(reference)] ?? null;
  }

  const noteCount = computed(() => Object.values(notes.value).filter(Boolean).length);

  watch(notes, (nextNotes) => {
    globalThis.localStorage?.setItem(NOTES_KEY, JSON.stringify(nextNotes));
  }, { deep: true });

  watch(highlights, (nextHighlights) => {
    globalThis.localStorage?.setItem(HIGHLIGHTS_KEY, JSON.stringify(nextHighlights));
  }, { deep: true });

  return {
    notes,
    highlights,
    noteCount,
    getReferenceKey,
    getNote,
    saveNote,
    toggleHighlight,
    getHighlight,
  };
});
