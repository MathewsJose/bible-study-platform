<template>
  <div class="min-h-screen px-4 pb-8 pt-4 sm:px-6 lg:px-8">
    <TopBar />

    <main class="mx-auto mt-3 w-full max-w-[1500px]">
      <slot />
    </main>
  </div>
</template>

<script setup>
import TopBar from './TopBar.vue';
</script>
