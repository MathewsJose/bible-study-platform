<template>
  <div class="panel-body">
    <div class="panel-header">
      <h2>Church Teachings</h2>
      <span v-if="teachingsStore.refreshing" class="panel-status">Updating...</span>
    </div>

    <AppLoader v-if="teachingsStore.loading" message="Loading teachings..." />
    <AppError
      v-else-if="teachingsStore.error && !teachingsStore.items.length"
      :message="teachingsStore.error"
    />
    <EmptyState
      v-else-if="!hasVerseSelected"
      message="Select a verse to see teachings and interpretations for the passage."
    />
    <EmptyState
      v-else-if="!teachingsStore.items.length"
      message="Teachings and interpretations will appear here for the selected passage."
    />
    <ul v-else class="panel-list">
      <li v-for="(item, index) in teachingsStore.items" :key="index">
        {{ item }}
      </li>
    </ul>
  </div>
</template>

<script setup>
import { storeToRefs } from 'pinia';
import { useTeachingsStore } from '../../stores/teachingsStore';
import { useSelectionStore } from '../../stores/selectionStore';
import AppLoader from '../common/AppLoader.vue';
import AppError from '../common/AppError.vue';
import EmptyState from '../common/EmptyState.vue';

const teachingsStore = useTeachingsStore();
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
