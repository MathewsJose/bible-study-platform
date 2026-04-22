<template>
  <span v-html="highlighted" />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  text: {
    type: String,
    required: true,
  },
  query: {
    type: String,
    default: '',
  },
});

function escapeHtml(value) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

const highlighted = computed(() => {
  const text = props.text ?? '';
  const query = props.query.trim();

  if (!query) {
    return escapeHtml(text);
  }

  const pattern = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
  return escapeHtml(text).replace(
    pattern,
    '<mark class="rounded bg-amber-200/70 px-1 text-slate-900 dark:bg-amber-300/80">$1</mark>'
  );
});
</script>
