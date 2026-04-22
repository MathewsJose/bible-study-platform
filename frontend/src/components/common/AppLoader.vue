<template>
  <div class="flex items-center gap-3 rounded-2xl border border-[var(--stroke)] bg-white/80 px-4 py-4 text-sm text-slate-600 dark:bg-slate-950/70 dark:text-slate-300">
    <span class="h-2.5 w-2.5 rounded-full bg-[var(--accent)] animate-pulse" />
    <span>{{ message }}</span>
  </div>
</template>

<script setup>
defineProps({
  message: {
    type: String,
    default: 'Loading...',
  },
});
</script>

