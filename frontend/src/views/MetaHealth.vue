<script setup>
import { computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { MetaApi } from '@/api/endpoints';

const qc = useQueryClient();
const { data, isLoading } = useQuery({ queryKey: ['meta-health'], queryFn: () => MetaApi.health(), refetchInterval: 30000 });
const h = computed(() => data.value ?? {});
const errors = computed(() => h.value.recent_errors ?? []);

const badge = (s) => ({ connected: 'ok', disconnected: 'off', error: 'danger', expired: 'danger' }[s] || 'off');
const headroom = computed(() => (h.value.rate_limit_headroom == null ? null : h.value.rate_limit_headroom));
const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 19) : '—');

const { mutate: connect, isPending } = useMutation({
  mutationFn: () => MetaApi.connect(),
  onSuccess: (res) => {
    if (res.configured && res.url) { window.location.href = res.url; }
    else { alert(res.message || 'Meta is not configured yet.'); }
    qc.invalidateQueries({ queryKey: ['meta-health'] });
  },
});
</script>

<template>
  <section class="stack">
    <header><h2>Meta Integration</h2><p class="muted">Connection health for your linked Meta assets (report-only: ads_read).</p></header>

    <div class="card stack">
      <div class="row" style="justify-content: space-between; align-items: center;">
        <div class="row" style="gap: 10px; align-items: center;">
          <span class="badge" :class="badge(h.status)">{{ h.status || '…' }}</span>
          <span v-if="h.scopes_granted?.length" class="muted em">scopes: {{ h.scopes_granted.join(', ') }}</span>
        </div>
        <button v-if="!h.connected" class="btn" :disabled="isPending" @click="connect()">{{ isPending ? 'Starting…' : 'Connect Meta' }}</button>
      </div>

      <div class="grid">
        <div class="stat"><span class="l">Last successful call</span><strong>{{ fmt(h.last_success_at) }}</strong></div>
        <div class="stat"><span class="l">Rate-limit headroom</span>
          <div v-if="headroom !== null" class="bar"><div class="fill" :class="{ warn: headroom < 20 }" :style="{ width: headroom + '%' }" /></div>
          <strong v-else>—</strong>
          <span v-if="headroom !== null" class="muted em">{{ headroom }}% remaining</span>
        </div>
        <div class="stat"><span class="l">Last error</span><strong :class="{ err: h.last_error }">{{ h.last_error || 'None' }}</strong><span class="muted em">{{ fmt(h.last_error_at) }}</span></div>
      </div>
    </div>

    <div class="card">
      <h3>Recent errors</h3>
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>When</th><th>Endpoint</th><th>HTTP</th><th>Code</th><th>Message</th></tr></thead>
        <tbody>
          <tr v-for="(e, i) in errors" :key="i">
            <td class="muted">{{ fmt(e.created_at) }}</td><td class="mono">{{ e.endpoint }}</td>
            <td>{{ e.http_status || '—' }}</td><td>{{ e.error_code || '—' }}</td><td class="err">{{ e.error_message }}</td>
          </tr>
          <tr v-if="!errors.length"><td colspan="5" class="muted" style="text-align:center;padding:14px;">No errors — integration healthy 🎉</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.stat { display: flex; flex-direction: column; gap: 4px; }
.stat .l { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.bar { height: 8px; background: var(--bg); border-radius: 999px; overflow: hidden; margin: 4px 0; }
.fill { height: 100%; background: #0b7a68; } .fill.warn { background: var(--danger); }
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.badge.ok { background: #e6f6f2; color: #0b7a68; } .badge.off { background: #eee; color: var(--muted); } .badge.danger { background: #fdecea; color: var(--danger); }
.mono { font-family: monospace; font-size: 12px; } .err { color: var(--danger); } .em { font-size: 12px; }
h3 { margin: 0 0 8px; }
</style>
