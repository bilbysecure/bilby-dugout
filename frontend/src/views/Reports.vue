<script setup>
import { reactive, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { ReportsApi, MediaApi } from '@/api/endpoints';

const qc = useQueryClient();
const { data, isLoading } = useQuery({ queryKey: ['reports'], queryFn: () => ReportsApi.list(), refetchInterval: 8000 });
const reports = computed(() => data.value ?? []);

const TYPES = [
  { v: 'social_performance', l: 'Social performance' },
  { v: 'campaign_wrapup', l: 'Ad campaign wrap-up' },
  { v: 'delivery_summary', l: 'Delivery summary' },
];
const typeLabel = (t) => TYPES.find((x) => x.v === t)?.l || t;

const form = reactive({ type: 'social_performance', period_start: '', period_end: '', white_label: false });
const { mutate: request, isPending } = useMutation({
  mutationFn: () => ReportsApi.request({ ...form }),
  onSuccess: () => qc.invalidateQueries({ queryKey: ['reports'] }),
});

const badge = (s) => ({ ready: 'ok', failed: 'danger', pending: 'off', processing: 'info' }[s] || 'off');
const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '—');
async function download(mediaId) {
  try { const { url } = await MediaApi.signedUrl(mediaId); window.open(url, '_blank'); }
  catch { alert('Report not ready.'); }
}
</script>

<template>
  <section class="stack">
    <header><h2>Reports</h2><p class="muted">Branded performance &amp; delivery reports for your account.</p></header>

    <div class="card stack">
      <h3>Generate a report</h3>
      <div class="grid">
        <label class="stack"><span>Type</span><select v-model="form.type" class="input"><option v-for="t in TYPES" :key="t.v" :value="t.v">{{ t.l }}</option></select></label>
        <label class="stack"><span>Period start</span><input v-model="form.period_start" type="date" class="input" /></label>
        <label class="stack"><span>Period end</span><input v-model="form.period_end" type="date" class="input" /></label>
      </div>
      <label class="row" style="gap: 8px;"><input type="checkbox" v-model="form.white_label" /> White-label with my brand kit</label>
      <div><button class="btn" :disabled="isPending" @click="request()">{{ isPending ? 'Requesting…' : 'Generate report' }}</button></div>
    </div>

    <div class="card">
      <h3>Your reports</h3>
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>Type</th><th>Period</th><th>Status</th><th>Generated</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in reports" :key="r.id">
            <td>{{ typeLabel(r.type) }}<span v-if="r.white_label" class="tag">white-label</span></td>
            <td class="muted">{{ r.period_start || 'All' }}<span v-if="r.period_end"> – {{ r.period_end }}</span></td>
            <td><span class="badge" :class="badge(r.status)">{{ r.status }}</span></td>
            <td class="muted">{{ fmt(r.generated_at) }}</td>
            <td><button v-if="r.status === 'ready' && r.media_id" class="btn secondary small" @click="download(r.media_id)">View</button></td>
          </tr>
          <tr v-if="!reports.length"><td colspan="5" class="muted" style="text-align:center;padding:14px;">No reports yet — generate one above.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.badge.ok { background: #e6f6f2; color: #0b7a68; } .badge.off { background: #eee; color: var(--muted); }
.badge.info { background: #eef2fb; color: var(--primary); } .badge.danger { background: #fdecea; color: var(--danger); }
.tag { font-size: 10px; background: #eef2fb; color: var(--primary); padding: 1px 6px; border-radius: 999px; margin-left: 6px; }
.btn.small { font-size: 13px; padding: 5px 12px; }
h3 { margin: 0 0 8px; }
</style>
