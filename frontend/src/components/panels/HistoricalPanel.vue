<template>
  <aside class="panel-surface h-fit overflow-hidden xl:sticky xl:top-24">
    <div class="border-b border-[var(--stroke)] px-4 py-4">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.26em] text-[var(--accent)]">Historical Context</p>
          <h2 class="mt-1.5 text-lg font-semibold text-slate-900">Background & Setting</h2>
        </div>
        <span
          v-if="historyStore.refreshing"
          class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600"
        >
          Updating
        </span>
      </div>
      <p class="mt-2 text-sm leading-6 text-slate-500">
        Historical facts and contextual notes for the selected book, chapter, and verse.
      </p>
    </div>

    <div class="max-h-[calc(100vh-10rem)] overflow-y-auto px-4 py-4">
      <AppLoader v-if="historyStore.loading" message="Gathering historical insight..." />
      <AppError v-else-if="historyStore.error && !historyStore.items.length" :message="historyStore.error" />
      <EmptyState
        v-else-if="!hasVerseSelected"
        message="Select a verse to populate this context column."
      />
      <EmptyState
        v-else-if="!historyStore.items.length"
        message="Historical insight will appear here when available."
      />
      <ul v-else class="space-y-2.5">
        <li
          v-for="(item, index) in historyStore.items"
          :key="index"
          class="rounded-xl bg-slate-50 px-3.5 py-3 text-sm leading-6 text-slate-600"
        >
          {{ item }}
        </li>
      </ul>
    </div>
  </aside>
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { useHistoryStore } from '../../stores/historyStore';
import { useSelectionStore } from '../../stores/selectionStore';
import AppLoader from '../common/AppLoader.vue';
import AppError from '../common/AppError.vue';
import EmptyState from '../common/EmptyState.vue';

const historyStore = useHistoryStore();
const selectionStore = useSelectionStore();
const { hasVerseSelected } = storeToRefs(selectionStore);
</script>
