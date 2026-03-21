<template>
  <div class="rounded-2xl border border-dashed border-[var(--stroke)] px-4 py-6 text-sm leading-7 text-slate-500 dark:text-slate-400">
    {{ message }}
  </div>
</template>

<script setup>
defineProps({
  message: {
    type: String,
    default: 'No data available.',
  },
});
</script>

