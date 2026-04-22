<template>
  <ReaderView />
</template>

<script setup>
import { useAsyncData } from '#imports';
import ReaderView from '../views/ReaderView.vue';
import { useReaderData } from '../composables/useReaderData';

const { ensureInitialData } = useReaderData();

await useAsyncData('reader-initial-data', async () => {
  await ensureInitialData();
  return true;
});
</script>
