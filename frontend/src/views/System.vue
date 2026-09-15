<script setup>
import { computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { SystemApi } from '@/api/endpoints';

const qc = useQueryClient();

const { data: overviewData, isLoading } = useQuery({
  queryKey: ['system-jobs'],
  queryFn: () => SystemApi.jobs(),
  refetchInterval: 5000,
});
const { data: failedData } = useQuery({
  queryKey: ['system-failed-jobs'],
  queryFn: () => SystemApi.failedJobs(),
  refetchInterval: 5000,
});

const overview = computed(() => overviewData.value ?? {});
const failed = computed(() => failedData.value ?? []);
const recent = computed(() => overview.value.recent ?? []);

const { mutate: replay, isPending: replaying } = useMutation({
  mutationFn: (id) => SystemApi.replay(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['system-jobs'] });
    qc.invalidateQueries({ queryKey: ['system-failed-jobs'] });
  },
});

const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 19) : '—');
</script>

<template>
  <section class="stack">
    <header>
      <h2>System / Jobs</h2>
      <p class="muted">Background queue health, failures, and replay.</p>
    </header>

    <div class="cards">
      <div class="card stat"><span class="n">{{ overview.queue_depth ?? '—' }}</span><span class="l">Queued</span></div>
      <div class="card stat"><span class="n">{{ overview.reserved ?? '—' }}</span><span class="l">Reserved</span></div>
      <div class="card stat danger"><span class="n">{{ overview.failed ?? '—' }}</span><span class="l">Failed</span></div>
    </div>

    <div class="card">
      <h3>Failed jobs</h3>
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>Job</th><th>Queue</th><th>Client</th><th>Attempts</th><th>Failed at</th><th>Error</th><th></th></tr></thead>
        <tbody>
          <tr v-for="f in failed" :key="f.id">
            <td>{{ f.name }}</td>
            <td>{{ f.queue }}</td>
            <td class="muted">{{ f.client_email || '—' }}</td>
            <td>{{ f.attempts }}</td>
            <td class="muted">{{ fmt(f.failed_at) }}</td>
            <td class="err" :title="f.error">{{ (f.error || '').slice(0, 80) }}</td>
            <td><button class="btn small" :disabled="replaying" @click="replay(f.id)">Replay</button></td>
          </tr>
          <tr v-if="!failed.length"><td colspan="7" class="muted" style="text-align: center; padding: 16px;">No failed jobs 🎉</td></tr>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Recent jobs</h3>
      <table class="tbl">
        <thead><tr><th>#</th><th>Job</th><th>Queue</th><th>Status</th><th>Attempts</th><th>Available at</th></tr></thead>
        <tbody>
          <tr v-for="j in recent" :key="j.id">
            <td class="muted">{{ j.id }}</td>
            <td>{{ j.name }}</td>
            <td>{{ j.queue }}</td>
            <td><span class="badge" :class="{ off: j.status !== 'pending' }">{{ j.status }}</span></td>
            <td>{{ j.attempts }} / {{ j.max_attempts }}</td>
            <td class="muted">{{ fmt(j.available_at) }}</td>
          </tr>
          <tr v-if="!recent.length"><td colspan="6" class="muted" style="text-align: center; padding: 16px;">Queue is empty.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.cards { display: flex; gap: 12px; flex-wrap: wrap; }
.stat { display: flex; flex-direction: column; gap: 4px; min-width: 120px; }
.stat .n { font-size: 26px; font-weight: 700; }
.stat .l { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.stat.danger .n { color: var(--danger); }
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.err { font-family: monospace; font-size: 12px; color: var(--danger); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.badge.off { background: #eef2fb; color: var(--primary); }
.btn.small { font-size: 12px; padding: 4px 10px; }
h3 { margin: 0 0 10px; }
</style>
