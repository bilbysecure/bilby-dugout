<script setup>
import { ref, computed, watch } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { CampaignsApi } from '@/api/endpoints';
import LineChart from '@/components/charts/LineChart.vue';

const { data: listData, isLoading } = useQuery({ queryKey: ['campaigns'], queryFn: () => CampaignsApi.list() });
const campaigns = computed(() => listData.value ?? []);

const selectedId = ref(null);
watch(campaigns, (c) => { if (!selectedId.value && c.length) selectedId.value = c[0].id; });

const { data: dashData } = useQuery({
  queryKey: ['campaign-dashboard', selectedId],
  queryFn: () => CampaignsApi.dashboard(selectedId.value),
  enabled: computed(() => !!selectedId.value),
});
const dash = computed(() => dashData.value ?? null);
const phases = computed(() => dash.value?.phases ?? []);
const trend = computed(() => dash.value?.daily_trend ?? []);
const alerts = computed(() => dash.value?.alerts ?? []);
const learning = computed(() => dash.value?.learning_status ?? []);

const spendPts = computed(() => trend.value.map((d) => ({ label: (d.metric_date || '').slice(5), value: Number(d.spend) })));
const leadPts = computed(() => trend.value.map((d) => ({ label: (d.metric_date || '').slice(5), value: Number(d.leads) })));
const cplPts = computed(() => trend.value.map((d) => ({ label: (d.metric_date || '').slice(5), value: Number(d.cpl) })));

const money = (n) => new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD', maximumFractionDigits: 0 }).format(Number(n || 0));
const abClass = (s) => ({ significant_winner: 'ok', significant_loser: 'danger', leading: 'lead', trailing: 'muted', control: 'ctrl' }[s] || 'muted');
const abLabel = (v) => ({ control: 'Control', leading: 'Leading', trailing: 'Trailing', significant_winner: '★ Winner', significant_loser: 'Underperforming', not_significant: 'Leading' }[v.status] || v.status);
const alertClass = (s) => (s === 'triggered' ? 'danger' : 'ok');
</script>

<template>
  <section class="stack">
    <header><h2>Ad Campaigns</h2><p class="muted">3-phase Meta lead-gen performance (report-only).</p></header>

    <div v-if="campaigns.length > 1" class="row" style="gap: 6px; flex-wrap: wrap;">
      <button v-for="c in campaigns" :key="c.id" class="chip" :class="{ on: selectedId === c.id }" @click="selectedId = c.id">{{ c.objective }} #{{ c.id }}</button>
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>
    <p v-else-if="!campaigns.length" class="muted">No campaigns yet.</p>

    <template v-if="dash">
      <!-- alerts -->
      <div v-if="alerts.some((a) => a.status === 'triggered')" class="card alerts">
        <strong>⚠ Alerts</strong>
        <ul>
          <li v-for="a in alerts.filter((x) => x.status === 'triggered')" :key="a.id">{{ a.last_message }}</li>
        </ul>
      </div>

      <!-- phase timeline -->
      <div class="card">
        <h3>Phase timeline</h3>
        <div class="timeline">
          <div v-for="ph in phases" :key="ph.id" class="phase" :style="{ flex: (Number(ph.budget_split) || 1) }">
            <strong>{{ ph.name }}</strong>
            <span class="muted em">{{ ph.start_date || '—' }} → {{ ph.end_date || '—' }} · {{ ph.budget_split || 0 }}%</span>
            <span class="muted em">{{ (ph.audiences || []).map((x) => x.audience_name).join(', ') || 'no audiences' }}</span>
          </div>
        </div>
      </div>

      <!-- trend charts -->
      <div class="charts">
        <div class="card"><h3>Spend</h3><LineChart :points="spendPts" /></div>
        <div class="card"><h3>Leads</h3><LineChart :points="leadPts" /></div>
        <div class="card"><h3>CPL</h3><LineChart :points="cplPts" /></div>
      </div>

      <!-- A/B per phase -->
      <div v-for="ph in phases.filter((p) => (p.ab || []).length)" :key="'ab' + ph.id" class="card">
        <h3>A/B — {{ ph.name }}</h3>
        <div class="abgrid">
          <div v-for="v in ph.ab" :key="v.variant_id" class="abcard" :class="abClass(v.status)">
            <div class="row" style="justify-content: space-between;">
              <strong>{{ v.headline }}</strong>
              <span class="tag">{{ abLabel(v) }}</span>
            </div>
            <div class="abstats">
              <span>{{ (Number(v.impressions)).toLocaleString() }} impr</span>
              <span>{{ v.leads }} leads</span>
              <span>{{ money(v.cpl) }} CPL</span>
              <span v-if="!v.is_control" :class="{ up: v.lift_pct > 0, down: v.lift_pct < 0 }">{{ v.lift_pct > 0 ? '+' : '' }}{{ v.lift_pct }}% vs control</span>
            </div>
            <p v-if="v.significance === 'insufficient_data'" class="muted em">Not enough data yet — directional only.</p>
          </div>
        </div>
      </div>

      <!-- learning status -->
      <div v-if="learning.length" class="card">
        <h3>Ad set learning status</h3>
        <div class="row" style="gap: 8px; flex-wrap: wrap;">
          <span v-for="l in learning" :key="l.adset_id" class="badge" :class="l.status === 'active' ? 'ok' : 'warn'">{{ l.adset_id }}: {{ l.status }} ({{ l.leads_7d }} leads/7d)</span>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.chip { border: 1px solid var(--border); background: var(--surface); padding: 5px 12px; border-radius: 999px; cursor: pointer; font-size: 13px; }
.chip.on { border-color: var(--primary); background: #eef2fb; color: var(--primary); font-weight: 600; }
.alerts { background: #fdf4f4; border: 1px solid #f0d0d0; } .alerts ul { margin: 6px 0 0; padding-left: 18px; }
.timeline { display: flex; gap: 8px; }
.phase { display: flex; flex-direction: column; gap: 3px; padding: 12px; border-radius: 10px; background: var(--bg); border-left: 4px solid var(--primary); min-width: 0; }
.charts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.abgrid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.abcard { border: 1px solid var(--border); border-radius: 10px; padding: 12px; }
.abcard.ok { border-color: #0b7a68; background: #f0faf7; } .abcard.danger { border-color: var(--danger); }
.abcard.ctrl { background: var(--bg); } .abcard.lead { border-color: var(--primary); }
.tag { font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #eef2fb; color: var(--primary); }
.abcard.ok .tag { background: #e6f6f2; color: #0b7a68; }
.abstats { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; color: var(--muted); margin-top: 6px; }
.abstats .up { color: #0b7a68; } .abstats .down { color: var(--danger); }
.badge.ok { background: #e6f6f2; color: #0b7a68; } .badge.warn { background: #fff4e0; color: #a15c00; }
.em { font-size: 11px; } h3 { margin: 0 0 8px; }
</style>
