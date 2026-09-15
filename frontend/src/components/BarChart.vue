<script setup>
import { computed } from 'vue';

// items: [{ label, value }]
const props = defineProps({ items: { type: Array, default: () => [] } });
const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
const fmt = (s) => String(s).replaceAll('_', ' ');
</script>

<template>
  <div class="bars">
    <div v-for="it in items" :key="it.label" class="bar-row">
      <span class="bar-label">{{ fmt(it.label) }}</span>
      <div class="bar-track">
        <div class="bar-fill" :style="{ width: `${(it.value / max) * 100}%` }" />
      </div>
      <span class="bar-value">{{ it.value }}</span>
    </div>
    <p v-if="!items.length" class="muted">No data.</p>
  </div>
</template>

<style scoped>
.bars { display: flex; flex-direction: column; gap: 8px; }
.bar-row { display: grid; grid-template-columns: 140px 1fr 36px; align-items: center; gap: 10px; }
.bar-label { font-size: 13px; text-transform: capitalize; color: var(--muted); }
.bar-track { background: var(--bg); border-radius: 6px; height: 16px; overflow: hidden; }
.bar-fill { height: 100%; background: var(--primary); border-radius: 6px; min-width: 2px; }
.bar-value { font-size: 13px; text-align: right; font-weight: 600; }
</style>
