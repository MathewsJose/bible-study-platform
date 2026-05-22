import { computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useBibleStore } from '../stores/bibleStore';
import { useHistoryStore } from '../stores/historyStore';
import { useTeachingsStore } from '../stores/teachingsStore';
import { useSelectionStore } from '../stores/selectionStore';
import { fetchStudyPayload } from '../services/studyService';

let initialized = false;

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
        currentSelection.chapter
      );
      historyStore.hydrateHistoricalData(
        payload.history || {},
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse
      );
      teachingsStore.hydrateTeachingsData(
        payload.teachings || {},
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse
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

    if (
      bibleStore.hasLoaded.value &&
      historyStore.hasLoaded.value &&
      teachingsStore.hasLoaded.value
    ) {
      return;
    }

    await syncSelection(currentSelection);
  }

  if (!initialized) {
    watch(selectionSnapshot, async (currentSelection, previousSelection) => {
      await syncSelection(currentSelection, previousSelection);
    });

    initialized = true;
  }

  return {
    selectionSnapshot,
    bibleStore,
    historyStore,
    teachingsStore,
    ensureInitialData,
  };
}
