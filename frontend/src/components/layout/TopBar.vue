<template>
  <header class="panel-surface sticky top-4 z-40 mx-auto flex w-full max-w-[1500px] flex-col gap-3 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
    <h1 class="text-lg font-semibold tracking-tight text-slate-900">
      Holy Bible Study Platform
    </h1>

    <div class="grid gap-2 sm:grid-cols-2">
      <label class="min-w-[220px]">
        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Book</span>
        <select
          v-model="selectedBookModel"
          class="soft-ring h-10 w-full rounded-xl border border-[var(--stroke)] bg-white px-3 text-sm font-medium text-slate-900"
        >
          <option v-for="book in books" :key="book" :value="book">{{ book }}</option>
        </select>
      </label>

      <label class="min-w-[140px]">
        <span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Chapter</span>
        <select
          v-model="selectedChapterModel"
          class="soft-ring h-10 w-full rounded-xl border border-[var(--stroke)] bg-white px-3 text-sm font-medium text-slate-900"
        >
          <option v-for="chapter in chapterOptions" :key="chapter" :value="chapter">{{ chapter }}</option>
        </select>
      </label>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useSelectionStore } from '../../stores/selectionStore';
import { BIBLE_BOOKS } from '../../utils/constants';

const selectionStore = useSelectionStore();
const { chapterOptions } = storeToRefs(selectionStore);
const books = BIBLE_BOOKS;

const selectedBookModel = computed({
  get: () => selectionStore.selectedBook,
  set: (value) => {
    selectionStore.setBook(value);
  },
});

const selectedChapterModel = computed({
  get: () => selectionStore.selectedChapter,
  set: (value) => {
    selectionStore.setChapter(value);
  },
});
</script>
