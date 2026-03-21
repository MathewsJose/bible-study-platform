<template>
  <header class="topbar">
    <div class="brand">Bible Study Platform</div>

    <div class="status" aria-live="polite">
      <span>{{ currentReference }}</span>
      <span
        v-if="
          bibleStore.refreshing ||
          historyStore.refreshing ||
          teachingsStore.refreshing
        "
      >
        Updating...
      </span>
    </div>

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

const { currentReference, chapterOptions } = storeToRefs(selectionStore);

const books = BIBLE_BOOKS;
const chapters = chapterOptions;

function onBookChange(event) {
  selectionStore.setBook(event.target.value);
}

function onChapterChange(event) {
  selectionStore.setChapter(event.target.value);
}
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
  flex-wrap: wrap;
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

.status {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.9rem;
  color: #475569;
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
