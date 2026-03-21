import { computed, ref, watch } from 'vue';
import { defineStore } from 'pinia';

const THEME_KEY = 'ui-theme';

function resolveInitialTheme() {
  const savedTheme = globalThis.localStorage?.getItem(THEME_KEY);
  if (savedTheme === 'light' || savedTheme === 'dark') {
    return savedTheme;
  }

  return globalThis.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export const useUiStore = defineStore('ui', () => {
  const theme = ref(resolveInitialTheme());
  const searchOpen = ref(false);
  const searchQuery = ref('');
  const searchFilter = ref('all');
  const activeVerse = ref(null);
  const toasts = ref([]);

  const isDark = computed(() => theme.value === 'dark');

  function applyTheme(nextTheme) {
    theme.value = nextTheme;
    if (typeof document !== 'undefined') {
      document.documentElement.classList.toggle('dark', nextTheme === 'dark');
      document.body.classList.toggle('dark', nextTheme === 'dark');
    }
  }

  function toggleTheme() {
    applyTheme(theme.value === 'dark' ? 'light' : 'dark');
  }

  function openSearch() {
    searchOpen.value = true;
  }

  function closeSearch() {
    searchOpen.value = false;
  }

  function setSearchFilter(filter) {
    searchFilter.value = filter;
  }

  function openVerseDrawer(payload) {
    activeVerse.value = payload;
  }

  function closeVerseDrawer() {
    activeVerse.value = null;
  }

  function pushToast(toast) {
    const id =
      globalThis.crypto?.randomUUID?.() ??
      `toast-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    toasts.value.push({ id, type: 'info', duration: 2800, ...toast });

    const duration = toast.duration ?? 2800;
    globalThis.setTimeout(() => {
      dismissToast(id);
    }, duration);
  }

  function dismissToast(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
  }

  watch(theme, (nextTheme) => {
    globalThis.localStorage?.setItem(THEME_KEY, nextTheme);
    applyTheme(nextTheme);
  }, { immediate: true });

  return {
    theme,
    isDark,
    searchOpen,
    searchQuery,
    searchFilter,
    activeVerse,
    toasts,
    toggleTheme,
    openSearch,
    closeSearch,
    setSearchFilter,
    openVerseDrawer,
    closeVerseDrawer,
    pushToast,
    dismissToast,
  };
});
