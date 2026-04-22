<template>
  <section class="panel-surface overflow-hidden">
    <div class="border-b border-[var(--stroke)] px-5 py-3">
      <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
        <h2 class="text-2xl font-semibold tracking-tight text-slate-900">
          {{ selectedBook }} {{ selectedChapter }}
        </h2>

        <div
          v-if="selectedVerse != null"
          class="rounded-xl bg-slate-50 px-3 py-1.5 text-sm text-slate-600"
        >
          <span class="font-semibold text-slate-900">{{ selectedChapter }}:{{ selectedVerse }}</span>
        </div>
      </div>

      <div
        v-if="bibleStore.sampleMode || bibleStore.copyrightNotice"
        class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm leading-6 text-amber-950"
      >
        <p class="font-semibold">
          {{ bibleStore.sampleMode ? 'Sample study summaries' : bibleStore.translation }}
        </p>
        <p v-if="bibleStore.copyrightNotice" class="mt-1">
          {{ bibleStore.copyrightNotice }}
        </p>
      </div>
    </div>

    <div v-if="bibleStore.loading" class="px-5 py-8">
      <AppLoader message="Loading Bible text..." />
    </div>
    <div v-else-if="bibleStore.error && !bibleStore.verses.length" class="space-y-3 px-5 py-6">
      <AppError :message="bibleStore.error" />
      <button
        type="button"
        class="soft-ring rounded-lg bg-rose-900 px-3 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-white"
        @click="bibleStore.retry"
      >
        Retry
      </button>
    </div>
    <div v-else-if="!bibleStore.verses.length" class="px-5 py-6">
      <EmptyState message="No verses are available for this chapter yet." />
    </div>
    <div v-else class="px-5 py-5">
      <div
        v-if="bibleStore.refreshing"
        class="mb-3 inline-flex items-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.24em] text-slate-600"
      >
        Updating chapter
      </div>
      <div
        v-if="bibleStore.error"
        class="mb-3 flex flex-col gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-950 sm:flex-row sm:items-center sm:justify-between"
      >
        <span>Could not refresh this chapter: {{ bibleStore.error }}</span>
        <button
          type="button"
          class="soft-ring rounded-lg bg-rose-900 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-white"
          @click="bibleStore.retry"
        >
          Retry
        </button>
      </div>

      <div class="mx-auto max-w-4xl">
        <VerseItem
          v-for="verse in bibleStore.verses"
          :key="verse.verse"
          :verse="verse"
          :selected="selectedVerse === verse.verse"
          @select="handleVerseSelect"
        />
      </div>
    </div>
  </section>
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
