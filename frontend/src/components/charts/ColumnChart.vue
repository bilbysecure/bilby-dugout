<script setup>
import { computed } from 'vue';

// items: [{ label, value, color }]
const props = defineProps({ items: { type: Array, default: () => [] } });

const W = 460, H = 210, padL = 26, padR = 10, padT = 12, padB = 28;
const plotW = W - padL - padR, plotH = H - padT - padB;

const max = computed(() => Math.max(8, ...props.items.map((i) => i.value)));
const ticks = computed(() => { const m = max.value; return [0, 1, 2, 3, 4].map((i) => Math.round((m * i) / 4)); });

const band = computed(() => plotW / Math.max(1, props.items.length));
const barW = computed(() => Math.min(48, band.value * 0.55));
const x = (i) => padL + band.value * i + (band.value - barW.value) / 2;
const y = (v) => padT + plotH - (v / max.value) * plotH;
const barH = (v) => (v / max.value) * plotH;
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="chart">
    <g v-for="t in ticks" :key="t">
      <line :x1="padL" :x2="W - padR" :y1="y(t)" :y2="y(t)" class="grid" />
      <text :x="padL - 6" :y="y(t) + 3" text-anchor="end" class="axis">{{ t }}</text>
    </g>
    <g v-for="(it, i) in items" :key="i">
      <rect :x="x(i)" :y="y(it.value)" :width="barW" :height="barH(it.value)" :fill="it.color" rx="4" />
      <text :x="x(i) + barW / 2" :y="H - 9" text-anchor="middle" class="axis cap">{{ it.label }}</text>
    </g>
  </svg>
</template>

<style scoped>
.chart { width: 100%; height: auto; }
.grid { stroke: var(--border); stroke-width: 1; stroke-dasharray: 3 3; }
.axis { fill: var(--muted); font-size: 10px; }
.cap { text-transform: capitalize; }
</style>
