<template>
  <transition name="fade">
    <div
      v-if="uiStore.searchOpen"
      class="fixed inset-0 z-[60] flex items-start justify-center bg-slate-950/40 px-4 py-24 backdrop-blur-sm"
      @click.self="uiStore.closeSearch"
    >
      <section class="panel-surface-strong w-full max-w-4xl overflow-hidden">
        <div class="border-b border-[var(--stroke)] p-4 sm:p-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <label class="relative flex-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15a7.5 7.5 0 0 1 0 15Z" />
              </svg>
              <input
                ref="searchInput"
                v-model="uiStore.searchQuery"
                type="search"
                placeholder="Search verses, themes, references..."
                class="soft-ring h-14 w-full rounded-2xl border border-[var(--stroke)] bg-slate-50 pl-12 pr-4 text-[15px] text-slate-900 placeholder:text-slate-400 dark:bg-slate-950 dark:text-slate-100"
              />
            </label>

            <div class="flex flex-wrap gap-2">
              <button
                v-for="filter in filters"
                :key="filter.value"
                type="button"
                class="soft-ring rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="uiStore.searchFilter === filter.value
                  ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'"
                @click="uiStore.setSearchFilter(filter.value)"
              >
                {{ filter.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="grid gap-6 p-4 sm:grid-cols-[1.3fr_0.9fr] sm:p-5">
          <div class="space-y-5">
            <section
              v-for="group in visibleGroups"
              :key="group.key"
              class="rounded-2xl bg-slate-50/90 p-4 dark:bg-slate-950/70"
            >
              <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
                  {{ group.label }}
                </h3>
                <span class="text-xs text-slate-400 dark:text-slate-500">{{ group.items.length }} results</span>
              </div>

              <div v-if="group.items.length" class="space-y-2">
                <button
                  v-for="item in group.items"
                  :key="item.id"
                  type="button"
                  class="soft-ring block w-full rounded-2xl border border-transparent bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-[var(--stroke)] hover:shadow-sm dark:bg-slate-900/80 dark:hover:border-slate-800"
                  @click="handleResult(item)"
                >
                  <p class="font-semibold text-slate-900 dark:text-slate-100">
                    <HighlightedText :text="item.title" :query="uiStore.searchQuery" />
                  </p>
                  <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                    <HighlightedText :text="item.subtitle" :query="uiStore.searchQuery" />
                  </p>
                </button>
              </div>

              <div v-else class="rounded-2xl border border-dashed border-[var(--stroke)] px-4 py-5 text-sm text-slate-500 dark:text-slate-400">
                No matches in this group yet.
              </div>
            </section>
          </div>

          <aside class="space-y-4">
            <section class="rounded-2xl bg-[var(--accent-soft)] p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--accent)]">Search Experience</p>
              <h3 class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-50">Fast, grouped, and readable</h3>
              <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                Results are organized by verse matches, study topics, and saved references for quick scanning.
              </p>
            </section>

            <section class="rounded-2xl border border-[var(--stroke)] p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Shortcuts</p>
              <ul class="mt-3 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                <li><span class="font-semibold text-slate-900 dark:text-slate-100">Search:</span> find verses in the current chapter instantly</li>
                <li><span class="font-semibold text-slate-900 dark:text-slate-100">Topics:</span> jump into guided themes and references</li>
                <li><span class="font-semibold text-slate-900 dark:text-slate-100">References:</span> revisit current and saved passages</li>
              </ul>
            </section>
          </aside>
        </div>
      </section>
    </div>
  </transition>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRouter } from '#imports';
import { useBibleStore } from '../../stores/bibleStore';
import { useBookmarkStore } from '../../stores/bookmarkStore';
import { useSelectionStore } from '../../stores/selectionStore';
import { useUiStore } from '../../stores/uiStore';
import { STUDY_TOPICS } from '../../utils/searchCatalog';
import HighlightedText from '../common/HighlightedText.vue';

const router = useRouter();
const bibleStore = useBibleStore();
const bookmarkStore = useBookmarkStore();
const selectionStore = useSelectionStore();
const uiStore = useUiStore();
const searchInput = ref(null);

const filters = [
  { label: 'All', value: 'all' },
  { label: 'Verses', value: 'verses' },
  { label: 'Topics', value: 'topics' },
  { label: 'References', value: 'references' },
];

const normalizedQuery = computed(() => uiStore.searchQuery.trim().toLowerCase());

const verseResults = computed(() => {
  const query = normalizedQuery.value;
  return bibleStore.verses
    .filter((verse) => !query || verse.text.toLowerCase().includes(query))
    .slice(0, 8)
    .map((verse) => ({
      id: `verse-${verse.verse}`,
      group: 'verses',
      title: `${selectionStore.selectedBook} ${selectionStore.selectedChapter}:${verse.verse}`,
      subtitle: verse.text,
      action: () => {
        selectionStore.setVerse(verse.verse);
        uiStore.closeSearch();
        router.push('/');
      },
    }));
});

const topicResults = computed(() => {
  const query = normalizedQuery.value;
  return STUDY_TOPICS
    .filter((topic) => {
      if (!query) {
        return true;
      }

      const haystack = `${topic.title} ${topic.description}`.toLowerCase();
      return haystack.includes(query);
    })
    .map((topic) => ({
      id: `topic-${topic.title}`,
      group: 'topics',
      title: topic.title,
      subtitle: topic.description,
      action: () => {
        const reference = topic.references[0];
        selectionStore.setSelection(reference);
        uiStore.closeSearch();
        router.push('/');
      },
    }));
});

const referenceResults = computed(() => {
  const references = [
    {
      id: 'current-reference',
      title: `Current: ${selectionStore.currentReference}`,
      subtitle: 'Resume your current reading context',
      payload: {
        book: selectionStore.selectedBook,
        chapter: selectionStore.selectedChapter,
        verse: selectionStore.selectedVerse,
      },
    },
    ...bookmarkStore.bookmarks.map((bookmark) => ({
      id: `bookmark-${bookmark.book}-${bookmark.chapter}-${bookmark.verse}`,
      title: `${bookmark.book} ${bookmark.chapter}:${bookmark.verse}`,
      subtitle: 'Saved bookmark',
      payload: bookmark,
    })),
  ];

  return references
    .filter((reference) => {
      if (!normalizedQuery.value) {
        return true;
      }

      return `${reference.title} ${reference.subtitle}`.toLowerCase().includes(normalizedQuery.value);
    })
    .slice(0, 8)
    .map((reference) => ({
      ...reference,
      group: 'references',
      action: () => {
        selectionStore.setSelection(reference.payload);
        uiStore.closeSearch();
        router.push('/');
      },
    }));
});

const groupedResults = computed(() => [
  { key: 'verses', label: 'Verses', items: verseResults.value },
  { key: 'topics', label: 'Topics', items: topicResults.value },
  { key: 'references', label: 'References', items: referenceResults.value },
]);

const visibleGroups = computed(() => {
  if (uiStore.searchFilter === 'all') {
    return groupedResults.value;
  }

  return groupedResults.value.filter((group) => group.key === uiStore.searchFilter);
});

function handleResult(item) {
  item.action();
}

watch(() => uiStore.searchOpen, async (open) => {
  if (open) {
    await nextTick();
    searchInput.value?.focus();
  }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.18s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
