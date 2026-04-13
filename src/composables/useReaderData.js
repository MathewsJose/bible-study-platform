import { computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useBibleStore } from '../stores/bibleStore';
import { useHistoryStore } from '../stores/historyStore';
import { useTeachingsStore } from '../stores/teachingsStore';
import { useSelectionStore } from '../stores/selectionStore';

let initialized = false;

export function useReaderData() {
  const selectionStore = useSelectionStore();
  const bibleStore = useBibleStore();
  const historyStore = useHistoryStore();
  const teachingsStore = useTeachingsStore();

  const { selectedBook, selectedChapter, selectedVerse } = storeToRefs(selectionStore);

  const selectionSnapshot = computed(() => ({
    book: selectedBook.value,
    chapter: selectedChapter.value,
    verse: selectedVerse.value,
  }));

  async function syncSelection(currentSelection, previousSelection = null) {
    const isPassageChange =
      !previousSelection ||
      currentSelection.book !== previousSelection.book ||
      currentSelection.chapter !== previousSelection.chapter;

    if (isPassageChange) {
      await bibleStore.loadBibleContent(currentSelection.book, currentSelection.chapter);
    }

    await Promise.all([
      historyStore.loadHistoricalData(
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse
      ),
      teachingsStore.loadTeachingsData(
        currentSelection.book,
        currentSelection.chapter,
        currentSelection.verse
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
