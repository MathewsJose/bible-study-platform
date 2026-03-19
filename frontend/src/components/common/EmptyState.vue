<template>
  <div class="empty">{{ message }}</div>
</template>

<script setup>
defineProps({
  message: {
    type: String,
    default: 'No data available.',
  },
});
</script>

<style scoped>
.empty {
  padding: 1rem;
  color: #6b7280;
}
</style>