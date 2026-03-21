import tailwindcss from '@tailwindcss/vite';

export default defineNuxtConfig({
  srcDir: 'src/',
  compatibilityDate: '2026-03-21',
  devtools: { enabled: false },
  modules: ['@pinia/nuxt'],
  css: ['~/style.css'],
  vite: {
    plugins: [tailwindcss()],
  },
  imports: {
    dirs: ['stores', 'composables', 'services', 'utils'],
  },
  pinia: {
    storesDirs: ['./stores/**'],
  },
  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || process.env.VITE_API_BASE_URL || '',
    },
  },
});
