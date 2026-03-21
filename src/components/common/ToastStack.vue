<template>
  <div class="pointer-events-none fixed bottom-6 right-6 z-[70] flex w-full max-w-sm flex-col gap-3 px-4">
    <transition-group name="toast">
      <article
        v-for="toast in uiStore.toasts"
        :key="toast.id"
        class="pointer-events-auto rounded-2xl border border-white/50 bg-white/90 p-4 text-sm shadow-2xl backdrop-blur dark:border-slate-800 dark:bg-slate-900/90"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ toast.title }}</p>
            <p class="mt-1 text-slate-600 dark:text-slate-300">{{ toast.message }}</p>
          </div>
          <button
            type="button"
            class="soft-ring rounded-full p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-100"
            @click="uiStore.dismissToast(toast.id)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6 6 18M6 6l12 12" />
            </svg>
          </button>
        </div>
      </article>
    </transition-group>
  </div>
</template>

<script setup>
import { useUiStore } from '../../stores/uiStore';

const uiStore = useUiStore();
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.24s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(12px);
}
</style>
