<template>
  <div class="mx-auto max-w-[1280px] space-y-8">
    <section class="grid gap-6 xl:grid-cols-[1.25fr_0.95fr]">
      <div class="panel-surface overflow-hidden px-6 py-8 sm:px-8 sm:py-10">
        <div class="max-w-2xl">
          <p class="eyebrow">Read. Study. Search. Reflect.</p>
          <h1 class="mt-5 max-w-3xl text-4xl font-semibold tracking-tight text-slate-900 dark:text-slate-50 sm:text-5xl lg:text-6xl">
            A modern Bible study space built for clarity, depth, and steady focus.
          </h1>
          <p class="mt-5 max-w-2xl text-base leading-8 text-slate-600 dark:text-slate-300 sm:text-lg">
            Move from reading to reflection without friction. Search quickly, open rich verse detail, save notes and bookmarks, and keep your study flow calm and distraction-free.
          </p>

          <div class="mt-8 flex flex-wrap gap-3">
            <NuxtLink
              to="/"
              class="soft-ring inline-flex items-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/20 transition hover:-translate-y-0.5 dark:bg-white dark:text-slate-950"
            >
              Open Reading Room
            </NuxtLink>
            <button
              type="button"
              class="soft-ring inline-flex items-center rounded-2xl border border-[var(--stroke)] bg-white/80 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:bg-white dark:bg-slate-950/70 dark:text-slate-200 dark:hover:bg-slate-950"
              @click="uiStore.openSearch"
            >
              Search Scriptures
            </button>
          </div>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-3">
          <article
            v-for="feature in features"
            :key="feature.title"
            class="rounded-3xl border border-white/60 bg-white/80 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950/70"
          >
            <p class="text-sm font-semibold text-[var(--accent)]">{{ feature.kicker }}</p>
            <h2 class="mt-3 text-xl font-semibold text-slate-900 dark:text-slate-50">{{ feature.title }}</h2>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ feature.description }}</p>
          </article>
        </div>
      </div>

      <div class="space-y-6">
        <section class="panel-surface px-6 py-6">
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[var(--accent)]">Study Snapshot</p>
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <article class="rounded-2xl bg-white/80 p-5 dark:bg-slate-950/70">
              <p class="text-sm text-slate-500 dark:text-slate-400">Bookmarks</p>
              <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ bookmarkStore.bookmarks.length }}</p>
            </article>
            <article class="rounded-2xl bg-white/80 p-5 dark:bg-slate-950/70">
              <p class="text-sm text-slate-500 dark:text-slate-400">Notes</p>
              <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ studyStore.noteCount }}</p>
            </article>
          </div>
        </section>

        <section class="panel-surface px-6 py-6">
          <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[var(--accent)]">Reading Progress</p>
          <h2 class="mt-3 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ selectionStore.currentReference }}</h2>
          <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
            Resume the passage you last explored and continue into related context and commentary without leaving the reading view.
          </p>
          <NuxtLink
            to="/"
            class="soft-ring mt-5 inline-flex items-center rounded-2xl border border-[var(--stroke)] bg-white/80 px-4 py-3 text-sm font-semibold text-slate-800 transition hover:-translate-y-0.5 hover:bg-white dark:bg-slate-950/70 dark:text-slate-200 dark:hover:bg-slate-950"
          >
            Continue Where You Left Off
          </NuxtLink>
        </section>
      </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
      <article class="panel-surface px-6 py-6">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[var(--accent)]">Recent Bookmarks</p>
        <div v-if="bookmarkStore.bookmarks.length" class="mt-4 space-y-3">
          <button
            v-for="bookmark in bookmarkStore.bookmarks.slice(0, 4)"
            :key="`${bookmark.book}-${bookmark.chapter}-${bookmark.verse}`"
            type="button"
            class="soft-ring flex w-full items-center justify-between rounded-2xl bg-white/80 px-4 py-4 text-left transition hover:-translate-y-0.5 hover:bg-white dark:bg-slate-950/70 dark:hover:bg-slate-950"
            @click="openBookmark(bookmark)"
          >
            <div>
              <p class="font-semibold text-slate-900 dark:text-slate-100">{{ bookmark.book }} {{ bookmark.chapter }}:{{ bookmark.verse }}</p>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Saved for quick return</p>
            </div>
            <span class="rounded-full bg-[var(--accent-soft)] px-3 py-1 text-xs font-semibold text-[var(--accent)]">Open</span>
          </button>
        </div>
        <div
          v-else
          class="mt-4 rounded-2xl border border-dashed border-[var(--stroke)] px-4 py-6 text-sm leading-7 text-slate-500 dark:text-slate-400"
        >
          No bookmarks yet. Save passages from the reading room to build your personal study dashboard.
        </div>
      </article>

      <article class="panel-surface px-6 py-6">
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[var(--accent)]">How This Workspace Helps</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div
            v-for="item in studyBenefits"
            :key="item.title"
            class="rounded-2xl bg-white/80 p-5 dark:bg-slate-950/70"
          >
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ item.title }}</h3>
            <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ item.body }}</p>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>

<script setup>
import { useRouter } from '#imports';
import { useBookmarkStore } from '../stores/bookmarkStore';
import { useSelectionStore } from '../stores/selectionStore';
import { useStudyStore } from '../stores/studyStore';
import { useUiStore } from '../stores/uiStore';

const router = useRouter();
const bookmarkStore = useBookmarkStore();
const selectionStore = useSelectionStore();
const studyStore = useStudyStore();
const uiStore = useUiStore();

const features = [
  {
    kicker: 'Read',
    title: 'Comfort-first typography',
    description: 'Scripture sits in a spacious, refined reading column with subtle verse interactions and visual rhythm.',
  },
  {
    kicker: 'Study',
    title: 'Context beside the text',
    description: 'Historical and commentary panels stay visible so the study journey feels connected rather than fragmented.',
  },
  {
    kicker: 'Reflect',
    title: 'Notes, highlights, bookmarks',
    description: 'Capture insight without interrupting the reading experience, then return to saved passages from anywhere.',
  },
];

const studyBenefits = [
  {
    title: 'Search with intent',
    body: 'Use the global search bar to jump into verses, references, and guided topics from one elegant overlay.',
  },
  {
    title: 'Stay in flow',
    body: 'Background loading keeps the interface stable while new data updates, so the app always feels like an SPA.',
  },
  {
    title: 'Study deeper',
    body: 'Open the verse drawer to compare translations, annotate your thoughts, and keep commentary close at hand.',
  },
  {
    title: 'Return quickly',
    body: 'Saved bookmarks and notes turn the app into a true personal study workspace, not just a verse browser.',
  },
];

function openBookmark(bookmark) {
  selectionStore.setSelection(bookmark);
  router.push('/');
}
</script>
