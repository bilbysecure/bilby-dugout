<script setup>
import { computed } from 'vue';

// points: [{ label, value }]
const props = defineProps({ points: { type: Array, default: () => [] } });

const W = 460, H = 200, padL = 28, padR = 12, padT = 12, padB = 26;
const plotW = W - padL - padR, plotH = H - padT - padB;

const max = computed(() => Math.max(4, ...props.points.map((p) => p.value)));
const ticks = computed(() => { const m = max.value; return [0, 1, 2, 3, 4].map((i) => Math.round((m * i) / 4)); });

const xs = (i) => padL + (props.points.length <= 1 ? 0 : (i / (props.points.length - 1)) * plotW);
const ys = (v) => padT + plotH - (v / max.value) * plotH;

const line = computed(() => props.points.map((p, i) => `${xs(i)},${ys(p.value)}`).join(' '));
const labelEvery = computed(() => Math.ceil(props.points.length / 5));
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="chart">
    <!-- gridlines + y labels -->
    <g v-for="t in ticks" :key="t">
      <line :x1="padL" :x2="W - padR" :y1="ys(t)" :y2="ys(t)" class="grid" />
      <text :x="padL - 6" :y="ys(t) + 3" text-anchor="end" class="axis">{{ t }}</text>
    </g>
    <!-- line -->
    <polyline :points="line" class="line" fill="none" />
    <!-- points + x labels -->
    <g v-for="(p, i) in points" :key="i">
      <circle :cx="xs(i)" :cy="ys(p.value)" r="3.5" class="dot" />
      <text v-if="i % labelEvery === 0" :x="xs(i)" :y="H - 8" text-anchor="middle" class="axis">{{ p.label }}</text>
    </g>
  </svg>
</template>

<style scoped>
.chart { width: 100%; height: auto; }
.grid { stroke: var(--border); stroke-width: 1; stroke-dasharray: 3 3; }
.axis { fill: var(--muted); font-size: 10px; }
.line { stroke: var(--primary); stroke-width: 2; }
.dot { fill: #fff; stroke: var(--primary); stroke-width: 2; }
</style>
