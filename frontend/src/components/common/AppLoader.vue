<template>
  <div class="state">{{ message }}</div>
</template>

<script setup>
defineProps({
  message: {
    type: String,
    default: 'Loading...',
  },
});
</script>

<style scoped>
.state {
  padding: 1rem;
  color: #64748b;
}
</style>