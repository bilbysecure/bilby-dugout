<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { useRequestsQuery } from '@/composables/queries';
import { AnalyticsApi } from '@/api/endpoints';
import StatCard from '@/components/StatCard.vue';
import BarChart from '@/components/BarChart.vue';

const { data, isLoading } = useRequestsQuery();
const requests = computed(() => data.value ?? []);

// Content performance (from post_metrics).
const { data: perfData } = useQuery({ queryKey: ['content-performance'], queryFn: () => AnalyticsApi.contentPerformance() });
const perf = computed(() => perfData.value ?? { totals: {}, by_platform: {}, top_posts: [], tracked_targets: 0 });
const num = (n) => new Intl.NumberFormat().format(Number(n || 0));

function distribution(field) {
  const counts = {};
  for (const r of requests.value) {
    const key = r[field] || 'unknown';
    counts[key] = (counts[key] ?? 0) + 1;
  }
  return Object.entries(counts)
    .map(([label, value]) => ({ label, value }))
    .sort((a, b) => b.value - a.value);
}

const byStatus = computed(() => distribution('status'));
const byType = computed(() => distribution('type'));
const byPriority = computed(() => distribution('priority'));

const total = computed(() => requests.value.length);
const completed = computed(() => requests.value.filter((r) => r.status === 'completed').length);
const active = computed(() =>
  requests.value.filter((r) => ['submitted', 'in_progress', 'review', 'revision'].includes(r.status)).length,
);
const completionRate = computed(() => (total.value ? Math.round((completed.value / total.value) * 100) : 0));
</script>

<template>
  <section class="stack">
    <header>
      <h2>Analytics</h2>
      <p class="muted">Computed from the requests visible to your role.</p>
    </header>

    <div class="card cperf">
      <div class="row" style="justify-content: space-between; align-items: baseline;">
        <h3>Content performance</h3>
        <span class="muted" style="font-size: 12px;">{{ perf.tracked_targets }} published target{{ perf.tracked_targets === 1 ? '' : 's' }} tracked</span>
      </div>
      <div class="cgrid">
        <div class="cstat"><span class="n">{{ num(perf.totals.impressions) }}</span><span class="l">Impressions</span></div>
        <div class="cstat"><span class="n">{{ num(perf.totals.reach) }}</span><span class="l">Reach</span></div>
        <div class="cstat"><span class="n">{{ num(perf.totals.engagement) }}</span><span class="l">Engagement</span></div>
        <div class="cstat"><span class="n">{{ num(perf.totals.clicks) }}</span><span class="l">Clicks</span></div>
      </div>
      <p v-if="!perf.tracked_targets" class="muted em">No social metrics yet — they appear here once published posts are pulled from Meta.</p>
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>
    <template v-else>
      <div class="row" style="flex-wrap: wrap;">
        <StatCard label="Total requests" :value="total" />
        <StatCard label="Active" :value="active" />
        <StatCard label="Completed" :value="completed" />
        <StatCard label="Completion rate" :value="`${completionRate}%`" />
      </div>

      <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
        <div class="card stack">
          <strong>By status</strong>
          <BarChart :items="byStatus" />
        </div>
        <div class="card stack">
          <strong>By service type</strong>
          <BarChart :items="byType" />
        </div>
        <div class="card stack">
          <strong>By priority</strong>
          <BarChart :items="byPriority" />
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.cperf h3 { margin: 0 0 10px; }
.cgrid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.cstat { display: flex; flex-direction: column; gap: 2px; }
.cstat .n { font-size: 24px; font-weight: 700; }
.cstat .l { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.em { font-size: 12px; margin: 10px 0 0; }
</style>
