<script setup>
import { computed } from 'vue';
import LineChart from '@/components/charts/LineChart.vue';
import DonutChart from '@/components/charts/DonutChart.vue';
import ColumnChart from '@/components/charts/ColumnChart.vue';
import BarsChart from '@/components/charts/BarsChart.vue';
import { PALETTE, STATUS_COLORS, PRIORITY_COLORS, humanize } from '@/constants/chartColors';

const props = defineProps({ requests: { type: Array, default: () => [] } });

const dayKey = (d) => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;

// Submissions — last 14 days
const submissions = computed(() => {
  const counts = {};
  for (const r of props.requests) {
    if (!r.created_at) continue;
    const d = new Date(String(r.created_at).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) continue;
    d.setHours(0, 0, 0, 0);
    counts[dayKey(d)] = (counts[dayKey(d)] || 0) + 1;
  }
  const out = [];
  for (let i = 13; i >= 0; i--) {
    const d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() - i);
    out.push({ label: d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }), value: counts[dayKey(d)] || 0 });
  }
  return out;
});

function tally(field) {
  const c = {};
  for (const r of props.requests) { const k = r[field] || 'unknown'; c[k] = (c[k] || 0) + 1; }
  return c;
}

const byType = computed(() =>
  Object.entries(tally('type')).sort((a, b) => b[1] - a[1])
    .map(([k, v], i) => ({ label: humanize(k), value: v, color: PALETTE[i % PALETTE.length] })));

const byStatus = computed(() =>
  Object.entries(tally('status')).sort((a, b) => b[1] - a[1])
    .map(([k, v]) => ({ label: humanize(k), value: v, color: STATUS_COLORS[k] || '#94a3b8' })));

const PRIORITY_ORDER = ['low', 'normal', 'high', 'urgent'];
const byPriority = computed(() => {
  const c = tally('priority');
  return PRIORITY_ORDER.map((p) => ({ label: p, value: c[p] || 0, color: PRIORITY_COLORS[p] }));
});
</script>

<template>
  <div class="grid">
    <div class="card">
      <h3>Submissions — Last 14 Days</h3>
      <LineChart :points="submissions" />
    </div>
    <div class="card">
      <h3>Requests by Type</h3>
      <BarsChart :items="byType" />
    </div>
    <div class="card">
      <h3>Status Distribution</h3>
      <DonutChart :segments="byStatus" />
    </div>
    <div class="card">
      <h3>Priority Breakdown</h3>
      <ColumnChart :items="byPriority" />
    </div>
  </div>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.card h3 { margin: 0 0 12px; font-size: 16px; }
@media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
</style>
