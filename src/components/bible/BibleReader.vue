<template>
  <div class="reader">
    <div class="reader-header">
      <h1>{{ selectedBook }} {{ selectedChapter }}</h1>
    </div>

    <AppLoader v-if="bibleStore.loading" message="Loading Bible text..." />
    <AppError v-else-if="bibleStore.error" :message="bibleStore.error" />
    <EmptyState
      v-else-if="!bibleStore.verses.length"
      message="No verses available for this chapter."
    />
    <div v-else class="verses">
      <VerseItem
        v-for="verse in bibleStore.verses"
        :key="verse.verse"
        :verse="verse"
        :selected="selectedVerse === verse.verse"
        @select="handleVerseSelect"
      />
    </div>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { useSelectionStore } from '../../stores/selectionStore';
import { useBibleStore } from '../../stores/bibleStore';
import VerseItem from './VerseItem.vue';
import AppLoader from '../common/AppLoader.vue';
import AppError from '../common/AppError.vue';
import EmptyState from '../common/EmptyState.vue';

const selectionStore = useSelectionStore();
const bibleStore = useBibleStore();

const { selectedBook, selectedChapter, selectedVerse } = storeToRefs(selectionStore);

function handleVerseSelect(verseNumber) {
  selectionStore.setVerse(verseNumber);
}
</script>

<style scoped>
.reader {
  padding: 1.25rem;
}

.reader-header h1 {
  margin: 0 0 1rem;
  font-size: 1.5rem;
  color: #111827;
}

.verses {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
</style>