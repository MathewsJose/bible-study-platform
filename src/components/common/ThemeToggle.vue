<template>
  <button
    type="button"
    class="soft-ring inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/50 bg-white/70 text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-white dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-900"
    @click="uiStore.toggleTheme"
  >
    <svg
      v-if="uiStore.isDark"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      class="h-5 w-5"
    >
      <path d="M12 3v2.2M12 18.8V21M4.93 4.93l1.56 1.56M17.5 17.5l1.57 1.57M3 12h2.2M18.8 12H21M4.93 19.07l1.56-1.56M17.5 6.5l1.57-1.57M15.5 12a3.5 3.5 0 1 1-7 0a3.5 3.5 0 0 1 7 0Z" />
    </svg>
    <svg
      v-else
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      class="h-5 w-5"
    >
      <path d="M21 12.8A8.8 8.8 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
    </svg>
  </button>
</template>

<script setup>
import { useUiStore } from '../../stores/uiStore';

const uiStore = useUiStore();
</script>
