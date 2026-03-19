<template>
  <div class="error">{{ message }}</div>
</template>

<script setup>
defineProps({
  message: {
    type: String,
    default: 'Something went wrong.',
  },
});
</script>

<style scoped>
.error {
  padding: 1rem;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
}
</style>