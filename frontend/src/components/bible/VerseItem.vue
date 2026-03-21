<template>
  <article class="verse-item" :class="{ selected }">
    <button class="verse-body" type="button" @click="$emit('select', verse.verse)">
      <span class="verse-number">{{ verse.verse }}</span>
      <span class="verse-text">{{ verse.text }}</span>
    </button>
    <button class="bookmark-toggle" type="button" @click="$emit('toggle-bookmark', verse.verse)">
      {{ bookmarked ? 'Bookmarked' : 'Bookmark' }}
    </button>
  </article>
</template>

<script setup>
defineProps({
  verse: {
    type: Object,
    required: true,
  },
  selected: {
    type: Boolean,
    default: false,
  },
  bookmarked: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['select', 'toggle-bookmark']);
</script>

<style scoped>
.verse-item {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
  transition: all 0.2s ease;
  cursor: pointer;
}

.verse-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.selected {
  border-color: #94a3b8;
  background: #f8fafc;
}

.verse-body {
  text-align: left;
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
}

.verse-number {
  font-size: 0.8rem;
  font-weight: 700;
  color: #64748b;
  min-width: 24px;
}

.verse-text {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.05rem;
  line-height: 1.8;
  color: #111827;
}

.bookmark-toggle {
  align-self: flex-start;
  padding: 0.35rem 0.7rem;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  cursor: pointer;
}
</style>
