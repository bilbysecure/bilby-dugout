<script setup>
import { computed } from 'vue';

// items: [{ label, value, color }]
const props = defineProps({ items: { type: Array, default: () => [] } });
const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
</script>

<template>
  <div class="bars">
    <div v-for="it in items" :key="it.label" class="row">
      <span class="label">{{ it.label }}</span>
      <div class="track">
        <div class="fill" :style="{ width: `${(it.value / max) * 100}%`, background: it.color }" />
      </div>
      <span class="value">{{ it.value }}</span>
    </div>
    <p v-if="!items.length" class="muted">No data.</p>
  </div>
</template>

<style scoped>
.bars { display: flex; flex-direction: column; gap: 10px; }
.row { display: grid; grid-template-columns: 110px 1fr 24px; align-items: center; gap: 10px; }
.label { font-size: 12px; text-transform: capitalize; color: var(--muted); text-align: right; line-height: 1.2; }
.track { background: var(--bg); border-radius: 6px; height: 18px; overflow: hidden; }
.fill { height: 100%; border-radius: 6px; min-width: 3px; transition: width .3s; }
.value { font-size: 13px; font-weight: 700; text-align: right; }
</style>
