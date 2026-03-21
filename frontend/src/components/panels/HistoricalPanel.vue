<template>
  <div class="panel-body">
    <div class="panel-header">
      <h2>Historical Context</h2>
      <span v-if="historyStore.refreshing" class="panel-status">Updating...</span>
    </div>

    <AppLoader v-if="historyStore.loading" message="Loading historical context..." />
    <AppError
      v-else-if="historyStore.error && !historyStore.items.length"
      :message="historyStore.error"
    />
    <EmptyState
      v-else-if="!hasVerseSelected"
      message="Select a verse to see historical context for the current passage."
    />
    <EmptyState
      v-else-if="!historyStore.items.length"
      message="Historical context will appear here for the selected passage."
    />
    <ul v-else class="panel-list">
      <li v-for="(item, index) in historyStore.items" :key="index">
        {{ item }}
      </li>
    </ul>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { useHistoryStore } from '../../stores/historyStore';
import { useSelectionStore } from '../../stores/selectionStore';
import AppLoader from '../common/AppLoader.vue';
import AppError from '../common/AppError.vue';
import EmptyState from '../common/EmptyState.vue';

const historyStore = useHistoryStore();
const selectionStore = useSelectionStore();
const { hasVerseSelected } = storeToRefs(selectionStore);
</script>

<style scoped>
.panel-body {
  padding: 1rem;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
}

.panel-header h2 {
  margin: 0 0 1rem;
  font-size: 1rem;
}

.panel-status {
  color: #64748b;
  font-size: 0.85rem;
}

.panel-list {
  padding-left: 1rem;
  color: #334155;
  line-height: 1.7;
}
</style>
