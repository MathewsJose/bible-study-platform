<template>
  <div class="reader">
    <div class="reader-header">
      <div>
        <h1>{{ currentReference }}</h1>
        <p v-if="hasVerseSelected" class="reader-subtitle">
          Verse context and teachings update automatically for the active selection.
        </p>
        <p v-else class="reader-subtitle">
          Select a verse to view historical context, church teachings, and bookmark it.
        </p>
      </div>

      <button
        v-if="hasVerseSelected"
        class="bookmark-button"
        type="button"
        @click="toggleSelectedBookmark"
      >
        {{ selectedVerseBookmarked ? 'Remove Bookmark' : 'Save Bookmark' }}
      </button>
    </div>

    <AppLoader v-if="bibleStore.loading" message="Loading Bible text..." />
    <AppError
      v-else-if="bibleStore.error && !bibleStore.verses.length"
      :message="bibleStore.error"
    />
    <EmptyState
      v-else-if="!bibleStore.verses.length"
      message="No verses available for this chapter."
    />
    <div v-else>
      <div v-if="bookmarks.length" class="bookmark-strip">
        <button
          v-for="bookmark in bookmarks"
          :key="`${bookmark.book}-${bookmark.chapter}-${bookmark.verse}`"
          class="bookmark-chip"
          type="button"
          @click="jumpToBookmark(bookmark)"
        >
          {{ bookmark.book }} {{ bookmark.chapter }}:{{ bookmark.verse }}
        </button>
      </div>

      <div class="verses">
      <VerseItem
        v-for="verse in bibleStore.verses"
        :key="verse.verse"
        :verse="verse"
        :selected="selectedVerse === verse.verse"
        :bookmarked="isVerseBookmarked(verse.verse)"
        @select="handleVerseSelect"
        @toggle-bookmark="toggleVerseBookmark"
      />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useSelectionStore } from '../../stores/selectionStore';
import { useBibleStore } from '../../stores/bibleStore';
import { useBookmarkStore } from '../../stores/bookmarkStore';
import VerseItem from './VerseItem.vue';
import AppLoader from '../common/AppLoader.vue';
import AppError from '../common/AppError.vue';
import EmptyState from '../common/EmptyState.vue';

const selectionStore = useSelectionStore();
const bibleStore = useBibleStore();
const bookmarkStore = useBookmarkStore();

const {
  selectedBook,
  selectedChapter,
  selectedVerse,
  currentReference,
  hasVerseSelected,
} = storeToRefs(selectionStore);
const { bookmarks } = storeToRefs(bookmarkStore);

const selectedVerseBookmarked = computed(() => {
  if (selectedVerse.value == null) {
    return false;
  }

  return bookmarkStore.isBookmarked({
    book: selectedBook.value,
    chapter: selectedChapter.value,
    verse: selectedVerse.value,
  });
});

function handleVerseSelect(verseNumber) {
  selectionStore.setVerse(verseNumber);
}

function buildBookmarkPayload(verse) {
  return {
    book: selectedBook.value,
    chapter: selectedChapter.value,
    verse: Number(verse),
  };
}

function isVerseBookmarked(verse) {
  return bookmarkStore.isBookmarked(buildBookmarkPayload(verse));
}

function toggleVerseBookmark(verse) {
  bookmarkStore.toggleBookmark(buildBookmarkPayload(verse));
}

function toggleSelectedBookmark() {
  if (selectedVerse.value == null) {
    return;
  }

  bookmarkStore.toggleBookmark(buildBookmarkPayload(selectedVerse.value));
}

function jumpToBookmark(bookmark) {
  selectionStore.setSelection(bookmark);
}
</script>

<style scoped>
.reader {
  padding: 1.25rem;
}

.reader-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1rem;
}

.reader-header h1 {
  margin: 0;
  font-size: 1.5rem;
  color: #111827;
}

.reader-subtitle {
  margin: 0.35rem 0 0;
  color: #475569;
}

.bookmark-button,
.bookmark-chip {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #0f172a;
  border-radius: 999px;
  cursor: pointer;
}

.bookmark-button {
  padding: 0.7rem 1rem;
  white-space: nowrap;
}

.bookmark-strip {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.bookmark-chip {
  padding: 0.45rem 0.75rem;
}

.verses {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

@media (max-width: 768px) {
  .reader-header {
    flex-direction: column;
  }
}
</style>
