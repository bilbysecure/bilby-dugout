<script setup>
import { computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { GovernanceApi, MediaApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const qc = useQueryClient();
const isOwner = computed(() => auth.role === 'client_owner');

const { data: exportsData, isLoading } = useQuery({ queryKey: ['data-exports'], queryFn: () => GovernanceApi.listExports() });
const exportsList = computed(() => exportsData.value ?? []);

const { mutate: requestExport, isPending } = useMutation({
  mutationFn: () => GovernanceApi.requestExport(),
  onSuccess: () => qc.invalidateQueries({ queryKey: ['data-exports'] }),
});

const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 19) : '—');
async function download(mediaId) {
  try {
    const { url } = await MediaApi.signedUrl(mediaId);
    window.open(url, '_blank');
  } catch { alert('Export not ready yet.'); }
}
</script>

<template>
  <section class="stack">
    <header><h2>Privacy &amp; Your Data</h2><p class="muted">Export or manage the data we hold for your account.</p></header>

    <div v-if="isOwner" class="card stack">
      <div class="row" style="justify-content: space-between; align-items: center;">
        <div><strong>Export my data</strong><p class="muted" style="margin: 2px 0 0; font-size: 13px;">Compiles your requests, projects, tasks, brand kits and more into a downloadable archive.</p></div>
        <button class="btn" :disabled="isPending" @click="requestExport()">{{ isPending ? 'Requesting…' : 'Request export' }}</button>
      </div>
    </div>

    <div class="card">
      <h3>Export history</h3>
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>Requested</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <tr v-for="e in exportsList" :key="e.id">
            <td class="muted">{{ fmt(e.created_at) }}</td>
            <td><span class="badge" :class="e.status === 'ready' ? 'ok' : (e.status === 'failed' ? 'danger' : 'off')">{{ e.status }}</span></td>
            <td><button v-if="e.status === 'ready' && e.media_id" class="btn secondary small" @click="download(e.media_id)">Download</button></td>
          </tr>
          <tr v-if="!exportsList.length"><td colspan="3" class="muted" style="text-align:center;padding:14px;">No exports yet.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.badge.ok { background: #e6f6f2; color: #0b7a68; } .badge.off { background: #eee; color: var(--muted); } .badge.danger { background: #fdecea; color: var(--danger); }
.btn.small { font-size: 13px; padding: 5px 12px; }
h3 { margin: 0 0 8px; }
</style>
