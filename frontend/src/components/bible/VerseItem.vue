<template>
  <button
    type="button"
    class="soft-ring block w-full rounded-xl px-2 py-1.5 text-left transition"
    :class="selected ? 'bg-blue-50' : 'hover:bg-slate-50'"
    @click="$emit('select', verse.verse)"
  >
    <span class="scripture-font text-[1.08rem] leading-[2.05] text-slate-800">
      <span
        class="mr-2 inline-flex min-w-7 align-top text-sm font-semibold not-italic"
        :class="selected ? 'text-[var(--accent)]' : 'text-slate-400'"
      >
        {{ verse.verse }}
      </span>
      <span>{{ verse.text }}</span>
    </span>
  </button>
</template>

<script setup>
defineProps({
  verse: {
    type: Object,
    required: true,
  },
  selected: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['select']);
</script>
