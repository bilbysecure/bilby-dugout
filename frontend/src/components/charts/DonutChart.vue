<script setup>
import { computed } from 'vue';

// segments: [{ label, value, color }]
const props = defineProps({ segments: { type: Array, default: () => [] } });

const R = 54, C = 70, CIRC = 2 * Math.PI * R;
const total = computed(() => props.segments.reduce((s, x) => s + x.value, 0));

const arcs = computed(() => {
  if (!total.value) return [];
  let prev = 0;
  return props.segments.filter((s) => s.value > 0).map((s) => {
    const len = (s.value / total.value) * CIRC;
    const arc = { ...s, len, offset: -prev };
    prev += len;
    return arc;
  });
});
</script>

<template>
  <div class="donut-wrap">
    <svg viewBox="0 0 140 140" class="donut">
      <g v-if="total" transform="rotate(-90 70 70)">
        <circle
          v-for="(a, i) in arcs" :key="i"
          cx="70" cy="70" :r="R" fill="none"
          :stroke="a.color" stroke-width="18"
          :stroke-dasharray="`${a.len} ${CIRC}`" :stroke-dashoffset="a.offset"
        />
      </g>
      <circle v-else cx="70" cy="70" :r="R" fill="none" stroke="var(--border)" stroke-width="18" />
      <text x="70" y="66" text-anchor="middle" class="total">{{ total }}</text>
      <text x="70" y="82" text-anchor="middle" class="total-label">total</text>
    </svg>
    <div class="legend">
      <div v-for="s in segments" :key="s.label" class="leg">
        <span class="dot" :style="{ background: s.color }" />
        <span class="lname">{{ s.label }}</span>
        <span class="lval">{{ s.value }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.donut-wrap { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
.donut { width: 150px; height: 150px; flex: none; }
.total { font-size: 20px; font-weight: 700; fill: var(--text); }
.total-label { font-size: 9px; fill: var(--muted); text-transform: uppercase; }
.legend { display: flex; flex-direction: column; gap: 7px; flex: 1; min-width: 160px; }
.leg { display: grid; grid-template-columns: 14px 1fr auto; align-items: center; gap: 8px; font-size: 13px; }
.dot { width: 10px; height: 10px; border-radius: 50%; }
.lname { text-transform: capitalize; color: var(--text); }
.lval { font-weight: 700; }
</style>
