<script>
let seq = 0;
</script>

<script setup>
const props = defineProps({
  size: { type: [Number, String], default: 28 },
  title: { type: String, default: '' },
});
const id = `bm${++seq}`;
</script>

<template>
  <svg class="brand-mark" viewBox="0 0 96 96" :width="size" :height="size" :role="props.title ? 'img' : undefined" :aria-label="props.title || undefined" :aria-hidden="props.title ? undefined : 'true'" focusable="false">
    <defs>
      <clipPath :id="id"><path d="M14 28 A14 14 0 0 1 28 14 L42.2 14 A14 14 0 0 1 52.1 18.1 L77.9 43.9 A14 14 0 0 1 82 53.8 L82 68 A14 14 0 0 1 68 82 L28 82 A14 14 0 0 1 14 68 Z" /></clipPath>
    </defs>
    <path d="M14 28 A14 14 0 0 1 28 14 L42.2 14 A14 14 0 0 1 52.1 18.1 L77.9 43.9 A14 14 0 0 1 82 53.8 L82 68 A14 14 0 0 1 68 82 L28 82 A14 14 0 0 1 14 68 Z" fill="currentColor" />
    <path d="M48 12 L48 34 A14 14 0 0 0 62 48 L84 48 Z" fill="var(--signal)" :clip-path="`url(#${id})`" />
  </svg>
</template>

<style scoped lang="scss">
.brand-mark {
  display: block;
  flex: none;
  color: var(--text);
}
</style>
