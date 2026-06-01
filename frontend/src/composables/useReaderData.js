import { computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useBibleStore } from '../stores/bibleStore';
import { useHistoryStore } from '../stores/historyStore';
import { useTeachingsStore } from '../stores/teachingsStore';
import { useSelectionStore } from '../stores/selectionStore';
import { fetchStudyPayload } from '../services/studyService';

let stopSelectionWatch = null;

export function useReaderData() {
  const selectionStore = useSelectionStore();
  const bibleStore = useBibleStore();
  const historyStore = useHistoryStore();
  const teachingsStore = useTeachingsStore();

  const { selectedBook, selectedChapter, selectedVerse, selectedLanguage, selectedVersion } = storeToRefs(selectionStore);

  const selectionSnapshot = computed(() => ({
    book: selectedBook.value,
    chapter: selectedChapter.value,
    verse: selectedVerse.value,
    language: selectedLanguage.value,
    version: selectedVersion.value,
  }));

  async function syncSelection(currentSelection, previousSelection = null) {
    if (await syncStudySelection(currentSelection)) {
      return;
    }

    await syncSelectionFromSeparateEndpoints(currentSelection, previousSelection);
  }

  async function syncStudySelection(currentSelection) {
    try {
      const payload = await fetchStudyPayload(
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse,
        currentSelection.language,
        currentSelection.version
      );

      bibleStore.hydrateBibleContent(
        payload.bible || {},
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.language,
        currentSelection.version
      );
      historyStore.hydrateHistoricalData(
        payload.history || {},
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse,
        currentSelection.language,
        currentSelection.version
      );
      teachingsStore.hydrateTeachingsData(
        payload.teachings || {},
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse,
        currentSelection.language,
        currentSelection.version
      );

      return true;
    } catch {
      return false;
    }
  }

  async function syncSelectionFromSeparateEndpoints(currentSelection, previousSelection = null) {
    const isPassageChange =
      !previousSelection ||
      currentSelection.book !== previousSelection.book ||
      currentSelection.chapter !== previousSelection.chapter ||
      currentSelection.language !== previousSelection.language ||
      currentSelection.version !== previousSelection.version;

    if (isPassageChange) {
      await bibleStore.loadBibleContent(
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.language,
        currentSelection.version
      );
    }

    await Promise.all([
      historyStore.loadHistoricalData(
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse,
        currentSelection.language,
        currentSelection.version
      ),
      teachingsStore.loadTeachingsData(
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse,
        currentSelection.language,
        currentSelection.version
      ),
    ]);
  }

  async function ensureInitialData() {
    const currentSelection = selectionSnapshot.value;

    await syncSelection(currentSelection);
  }

  if (import.meta.client && stopSelectionWatch === null) {
    stopSelectionWatch = watch(
      [selectedBook, selectedChapter, selectedVerse, selectedLanguage, selectedVersion],
      async (currentValues, previousValues) => {
        const currentSelection = {
          book: currentValues[0],
          chapter: currentValues[1],
          verse: currentValues[2],
          language: currentValues[3],
          version: currentValues[4],
        };

        const previousSelection = previousValues
          ? {
              book: previousValues[0],
              chapter: previousValues[1],
              verse: previousValues[2],
              language: previousValues[3],
              version: previousValues[4],
            }
          : null;

        await syncSelection(currentSelection, previousSelection);
      }
    );
  }

  return {
    selectionSnapshot,
    bibleStore,
    historyStore,
    teachingsStore,
    ensureInitialData,
  };
}
