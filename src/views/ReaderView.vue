<template>
  <div class="space-y-3">
    <section class="grid gap-3 xl:grid-cols-[285px_minmax(0,1fr)_285px]">
      <HistoricalPanel />
      <BibleReader />
      <TeachingPanel />
    </section>
  </div>
</template>

<script setup>
import HistoricalPanel from '../components/panels/HistoricalPanel.vue';
import TeachingPanel from '../components/panels/TeachingPanel.vue';
import BibleReader from '../components/bible/BibleReader.vue';
import { useReaderData } from '../composables/useReaderData';

useReaderData();
</script>
