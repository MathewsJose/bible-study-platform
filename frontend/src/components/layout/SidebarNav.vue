<template>
  <aside class="panel-surface sticky top-24 hidden h-[calc(100vh-7rem)] overflow-hidden lg:flex lg:w-[288px] lg:flex-col">
    <div class="border-b border-[var(--stroke)] px-5 py-5">
      <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[var(--accent)]">Library</p>
      <h2 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Study calmly, move quickly</h2>
      <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
        A focused reading environment with elegant navigation, search, and saved study actions.
      </p>
    </div>

    <nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-4 py-5">
      <div class="space-y-2">
        <NuxtLink
          v-for="item in navItems"
          :key="item.name"
          :to="item.to"
          class="group flex items-center justify-between rounded-2xl px-4 py-3 text-sm font-medium transition"
          :class="route.name === item.name
            ? 'bg-[var(--accent-soft)] text-[var(--accent)]'
            : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/80 dark:hover:text-white'"
        >
          <span>{{ item.label }}</span>
          <span class="text-xs uppercase tracking-[0.18em] opacity-60">{{ item.badge }}</span>
        </NuxtLink>
      </div>

      <section class="rounded-2xl bg-[var(--surface-muted)] p-4 dark:bg-slate-900/70">
        <p class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-500 dark:text-slate-400">
          Current Passage
        </p>
        <p class="mt-3 text-lg font-semibold text-slate-900 dark:text-slate-100">
          {{ selectionStore.currentReference }}
        </p>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
          Quick access keeps your study flow uninterrupted.
        </p>
        <NuxtLink
          to="/reader"
          class="soft-ring mt-4 inline-flex items-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-0.5 dark:bg-white dark:text-slate-950"
        >
          Continue Reading
        </NuxtLink>
      </section>

      <section>
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-500 dark:text-slate-400">Bookmarks</p>
          <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-900 dark:text-slate-300">
            {{ bookmarkStore.bookmarks.length }}
          </span>
        </div>

        <div v-if="bookmarkStore.bookmarks.length" class="mt-3 space-y-2">
          <button
            v-for="bookmark in bookmarkStore.bookmarks.slice(0, 5)"
            :key="`${bookmark.book}-${bookmark.chapter}-${bookmark.verse}`"
            type="button"
            class="soft-ring flex w-full items-center justify-between rounded-2xl border border-transparent bg-white/65 px-4 py-3 text-left text-sm transition hover:-translate-y-0.5 hover:border-[var(--stroke)] hover:bg-white dark:bg-slate-950/70 dark:hover:border-slate-800 dark:hover:bg-slate-950"
            @click="goToBookmark(bookmark)"
          >
            <span class="font-medium text-slate-800 dark:text-slate-100">{{ bookmark.book }} {{ bookmark.chapter }}:{{ bookmark.verse }}</span>
            <span class="text-xs uppercase tracking-[0.18em] text-[var(--accent)]">Saved</span>
          </button>
        </div>

        <div
          v-else
          class="mt-3 rounded-2xl border border-dashed border-[var(--stroke)] px-4 py-5 text-sm leading-6 text-slate-500 dark:text-slate-400"
        >
          Save meaningful verses to keep them one click away.
        </div>
      </section>
    </nav>
  </aside>
</template>

<script setup>
import { useRoute, useRouter } from '#imports';
import { useBookmarkStore } from '../../stores/bookmarkStore';
import { useSelectionStore } from '../../stores/selectionStore';

const route = useRoute();
const router = useRouter();
const bookmarkStore = useBookmarkStore();
const selectionStore = useSelectionStore();

const navItems = [
  { name: 'home', label: 'Dashboard', badge: 'Home', to: '/' },
  { name: 'reader', label: 'Reading Room', badge: 'Core', to: '/reader' },
];

function goToBookmark(bookmark) {
  selectionStore.setSelection(bookmark);
  router.push('/reader');
}
</script>
