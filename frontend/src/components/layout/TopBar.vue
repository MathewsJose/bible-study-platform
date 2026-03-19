<template>
  <header class="topbar">
    <div class="brand">Bible Study Platform</div>

    <div class="controls">
      <label>
        <span>Book</span>
        <select :value="selectionStore.selectedBook" @change="onBookChange">
          <option v-for="book in books" :key="book" :value="book">
            {{ book }}
          </option>
        </select>
      </label>

      <label>
        <span>Chapter</span>
        <select :value="selectionStore.selectedChapter" @change="onChapterChange">
          <option v-for="chapter in chapters" :key="chapter" :value="chapter">
            {{ chapter }}
          </option>
        </select>
      </label>
    </div>
  </header>
</template>

<script setup>
import { computed, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useSelectionStore } from '../../stores/selectionStore';
import { useBibleStore } from '../../stores/bibleStore';
import { useHistoryStore } from '../../stores/historyStore';
import { useTeachingsStore } from '../../stores/teachingsStore';
import { BIBLE_BOOKS } from '../../utils/constants';

const selectionStore = useSelectionStore();
const bibleStore = useBibleStore();
const historyStore = useHistoryStore();
const teachingsStore = useTeachingsStore();

const { selectedBook, selectedChapter, selectedVerse } = storeToRefs(selectionStore);

const books = BIBLE_BOOKS;
const chapters = computed(() => Array.from({ length: 50 }, (_, i) => i + 1));

function onBookChange(event) {
  selectionStore.setBook(event.target.value);
}

function onChapterChange(event) {
  selectionStore.setChapter(event.target.value);
}

watch(
  [selectedBook, selectedChapter],
  async ([book, chapter]) => {
    await bibleStore.loadBibleContent(book, chapter);
    await Promise.all([
      historyStore.loadHistoricalData(book, chapter, selectedVerse.value),
      teachingsStore.loadTeachingsData(book, chapter, selectedVerse.value),
    ]);
  },
  { immediate: true }
);

watch(
  selectedVerse,
  async (verse) => {
    await Promise.all([
      historyStore.loadHistoricalData(selectedBook.value, selectedChapter.value, verse),
      teachingsStore.loadTeachingsData(selectedBook.value, selectedChapter.value, verse),
    ]);
  }
);
</script>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid #e5e7eb;
}

.brand {
  font-size: 1.1rem;
  font-weight: 700;
  color: #111827;
}

.controls {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: #4b5563;
}

select {
  min-width: 160px;
  padding: 0.65rem 0.75rem;
  border-radius: 10px;
  border: 1px solid #d1d5db;
  background: white;
}
</style>