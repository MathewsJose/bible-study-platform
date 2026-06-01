<template>
  <ReaderView />
</template>

<script setup>
import { useAsyncData, useRoute } from '#imports';
import { useSelectionStore } from '../stores/selectionStore';
import ReaderView from '../views/ReaderView.vue';
import { useReaderData } from '../composables/useReaderData';

const route = useRoute();
const selectionStore = useSelectionStore();
const { ensureInitialData } = useReaderData();

const book = String(route.query.book ?? 'John');
const chapter = Number(route.query.chapter ?? 3);
const verse = route.query.verse != null ? Number(route.query.verse) : null;
const version = String(route.query.version ?? 'cpdv');

if (book && chapter) {
  selectionStore.setSelection({ book, chapter, verse });
}

if (version) {
  selectionStore.setVersion(version);
}

await useAsyncData('reader-bible-route', async () => {
  await ensureInitialData();
  return true;
});
</script>
