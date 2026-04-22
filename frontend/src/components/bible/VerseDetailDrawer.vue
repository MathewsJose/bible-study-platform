<template>
  <transition name="drawer">
    <div
      v-if="uiStore.activeVerse"
      class="fixed inset-0 z-50 flex justify-end bg-slate-950/35 backdrop-blur-sm"
      @click.self="uiStore.closeVerseDrawer"
    >
      <aside class="flex h-full w-full max-w-2xl flex-col border-l border-white/10 bg-white/92 shadow-[var(--shadow-strong)] backdrop-blur-xl dark:bg-slate-950/92">
        <header class="border-b border-[var(--stroke)] px-6 py-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="eyebrow">Verse Detail</p>
              <h2 class="mt-3 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                {{ referenceLabel }}
              </h2>
              <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                Study translations, notes, highlights, commentary, and historical insight in one focused workspace.
              </p>
            </div>

            <button
              type="button"
              class="soft-ring rounded-2xl border border-[var(--stroke)] p-3 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-900 dark:hover:text-slate-100"
              @click="uiStore.closeVerseDrawer"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M18 6 6 18M6 6l12 12" />
              </svg>
            </button>
          </div>
        </header>

        <div class="flex flex-wrap gap-3 border-b border-[var(--stroke)] px-6 py-4">
          <button
            type="button"
            class="soft-ring rounded-full px-4 py-2 text-sm font-semibold transition"
            :class="bookmarkStore.isBookmarked(reference) ? 'bg-[var(--accent)] text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200'"
            @click="toggleBookmark"
          >
            {{ bookmarkStore.isBookmarked(reference) ? 'Bookmarked' : 'Add Bookmark' }}
          </button>
          <button
            type="button"
            class="soft-ring rounded-full px-4 py-2 text-sm font-semibold transition"
            :class="studyStore.getHighlight(reference) ? 'bg-amber-200 text-amber-950' : 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-200'"
            @click="toggleHighlight"
          >
            {{ studyStore.getHighlight(reference) ? 'Highlighted' : 'Highlight Verse' }}
          </button>
          <button
            v-for="tab in tabs"
            :key="tab"
            type="button"
            class="soft-ring rounded-full px-4 py-2 text-sm font-semibold transition"
            :class="activeTab === tab ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-300'"
            @click="activeTab = tab"
          >
            {{ tab }}
          </button>
        </div>

        <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
          <section class="panel-surface-strong p-6">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--accent)]">Selected Verse</p>
            <p class="scripture-font mt-4 text-2xl leading-[1.8] text-slate-900 dark:text-slate-100">
              {{ uiStore.activeVerse.text }}
            </p>
          </section>

          <section v-if="activeTab === 'Translations'" class="grid gap-4">
            <article
              v-for="translation in translationCards"
              :key="translation.label"
              class="panel-surface p-5"
            >
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ translation.label }}</p>
                  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ translation.caption }}</p>
                </div>
                <span class="rounded-full bg-[var(--accent-soft)] px-3 py-1 text-xs font-semibold text-[var(--accent)]">
                  {{ translation.badge }}
                </span>
              </div>
              <p class="scripture-font mt-4 text-lg leading-8 text-slate-800 dark:text-slate-100">
                {{ translation.text }}
              </p>
            </article>
          </section>

          <section v-else-if="activeTab === 'Commentary'" class="grid gap-4 md:grid-cols-2">
            <article class="panel-surface p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--accent)]">Church Commentary</p>
              <ul class="mt-4 space-y-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
                <li v-for="(item, index) in commentaryItems" :key="index">{{ item }}</li>
              </ul>
            </article>
            <article class="panel-surface p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--accent)]">Historical Insight</p>
              <ul class="mt-4 space-y-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
                <li v-for="(item, index) in historicalItems" :key="index">{{ item }}</li>
              </ul>
            </article>
          </section>

          <section v-else class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
            <article class="panel-surface p-5">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--accent)]">Private Notes</p>
                  <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Capture observations, questions, and prayers for this verse.
                  </p>
                </div>
                <button
                  type="button"
                  class="soft-ring rounded-full bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 dark:bg-white dark:text-slate-950"
                  @click="saveNote"
                >
                  Save Note
                </button>
              </div>

              <textarea
                v-model="noteDraft"
                rows="8"
                class="soft-ring mt-4 w-full rounded-2xl border border-[var(--stroke)] bg-white/85 p-4 text-sm leading-7 text-slate-800 placeholder:text-slate-400 dark:bg-slate-950/80 dark:text-slate-100"
                placeholder="Write your reflection, interpretation, or prayer..."
              />
            </article>

            <article class="panel-surface p-5">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--accent)]">Study Actions</p>
              <div class="mt-4 space-y-3 text-sm leading-7 text-slate-600 dark:text-slate-300">
                <p>Use bookmarks to save the passage, highlights to mark emphasis, and notes to build your study trail.</p>
                <p>These tools stay local to your browser for a fast, private workflow.</p>
              </div>
            </article>
          </section>
        </div>
      </aside>
    </div>
  </transition>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useBookmarkStore } from '../../stores/bookmarkStore';
import { useHistoryStore } from '../../stores/historyStore';
import { useStudyStore } from '../../stores/studyStore';
import { useTeachingsStore } from '../../stores/teachingsStore';
import { useUiStore } from '../../stores/uiStore';

const uiStore = useUiStore();
const bookmarkStore = useBookmarkStore();
const studyStore = useStudyStore();
const historyStore = useHistoryStore();
const teachingsStore = useTeachingsStore();
const { activeVerse } = storeToRefs(uiStore);
const noteDraft = ref('');
const tabs = ['Translations', 'Commentary', 'Notes'];
const activeTab = ref('Translations');

const reference = computed(() => ({
  book: activeVerse.value?.book,
  chapter: activeVerse.value?.chapter,
  verse: activeVerse.value?.verse,
}));

const referenceLabel = computed(() =>
  activeVerse.value
    ? `${activeVerse.value.book} ${activeVerse.value.chapter}:${activeVerse.value.verse}`
    : ''
);

const translationCards = computed(() => {
  const verseText = activeVerse.value?.text ?? '';
  return [
    { label: 'Reading Text', badge: 'Primary', caption: 'Current API reading text', text: verseText },
    { label: 'Study Layout', badge: 'Ready', caption: 'Prepared for alternate translation feeds', text: verseText },
    { label: 'Reflection View', badge: 'Focus', caption: 'A clean comparison surface for personal study', text: verseText },
  ];
});

const commentaryItems = computed(() =>
  teachingsStore.items.length
    ? teachingsStore.items
    : [
        'Commentary and theological notes will appear here when available for the selected verse.',
        'The drawer is designed to scale into richer translation and commentary sources later.',
      ]
);

const historicalItems = computed(() =>
  historyStore.items.length
    ? historyStore.items
    : [
        'Historical context appears here for background, culture, and timeline insight.',
        'The layout keeps this context close without crowding the reading flow.',
      ]
);

watch(activeVerse, (nextVerse) => {
  activeTab.value = 'Translations';
  noteDraft.value = nextVerse ? studyStore.getNote(nextVerse) : '';
}, { immediate: true });

function toggleBookmark() {
  const isSaved = bookmarkStore.toggleBookmark(reference.value);
  uiStore.pushToast({
    title: isSaved ? 'Bookmark saved' : 'Bookmark removed',
    message: `${referenceLabel.value} ${isSaved ? 'was added to' : 'was removed from'} your saved passages.`,
  });
}

function toggleHighlight() {
  const isHighlighted = studyStore.toggleHighlight(reference.value);
  uiStore.pushToast({
    title: isHighlighted ? 'Verse highlighted' : 'Highlight removed',
    message: `${referenceLabel.value} is ${isHighlighted ? 'now highlighted for review.' : 'back to its default state.'}`,
  });
}

function saveNote() {
  studyStore.saveNote(reference.value, noteDraft.value);
  uiStore.pushToast({
    title: 'Note saved',
    message: `Your reflection for ${referenceLabel.value} has been saved locally.`,
  });
}
</script>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: all 0.24s ease;
}

.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}

.drawer-enter-from aside,
.drawer-leave-to aside {
  transform: translateX(100%);
}

.drawer-enter-active aside,
.drawer-leave-active aside {
  transition: transform 0.24s ease;
}
</style>
