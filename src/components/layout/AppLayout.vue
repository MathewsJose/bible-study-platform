<template>
  <div class="app-shell">
    <TopBar />

    <main class="layout-grid">
      <aside class="panel left-panel">
        <HistoricalPanel />
      </aside>

      <section class="panel main-panel">
        <BibleReader />
      </section>

      <aside class="panel right-panel">
        <TeachingPanel />
      </aside>
    </main>
  </div>
</template>

<script setup>
import TopBar from './TopBar.vue';
import BibleReader from '../bible/BibleReader.vue';
import HistoricalPanel from '../panels/HistoricalPanel.vue';
import TeachingPanel from '../panels/TeachingPanel.vue';
import { useReaderData } from '../../composables/useReaderData';

useReaderData();
</script>

<style scoped>
.app-shell {
  min-height: 100vh;
  background: #f8fafc;
}

.layout-grid {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr) 280px;
  gap: 1rem;
  padding: 1rem;
}

.panel {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
  overflow: hidden;
  min-height: calc(100vh - 96px);
}

@media (max-width: 1024px) {
  .layout-grid {
    grid-template-columns: 1fr;
  }

  .panel {
    min-height: auto;
  }
}
</style>
