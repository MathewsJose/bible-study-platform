<template>
  <div class="panel-body">
    <div class="panel-header">
      <h2>Church Teachings</h2>
    </div>

    <AppLoader v-if="teachingsStore.loading" message="Loading teachings..." />
    <AppError v-else-if="teachingsStore.error" :message="teachingsStore.error" />
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
import { useTeachingsStore } from '../../stores/teachingsStore';
import AppLoader from '../common/AppLoader.vue';
import AppError from '../common/AppError.vue';
import EmptyState from '../common/EmptyState.vue';

const teachingsStore = useTeachingsStore();
</script>

<style scoped>
.panel-body {
  padding: 1rem;
}

.panel-header h2 {
  margin: 0 0 1rem;
  font-size: 1rem;
}

.panel-list {
  padding-left: 1rem;
  color: #334155;
  line-height: 1.7;
}
</style>