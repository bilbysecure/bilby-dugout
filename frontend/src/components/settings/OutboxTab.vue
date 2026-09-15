<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { OutboxApi } from '@/api/endpoints';

const { data, isLoading, refetch } = useQuery({ queryKey: ['outbox'], queryFn: () => OutboxApi.list() });
const rows = computed(() => data.value ?? []);
const fmt = (d) => (d ? new Date(d.replace(' ', 'T')).toLocaleString() : '');
</script>

<template>
  <div class="stack">
    <div class="row" style="justify-content: space-between;">
      <p class="muted" style="margin: 0;">Simulated email &amp; Teams messages (mock integrations — no real send).</p>
      <button class="btn secondary" @click="refetch()">Refresh</button>
    </div>

    <div class="card">
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>When</th><th>Channel</th><th>To</th><th>Subject</th><th>Type</th></tr></thead>
        <tbody>
          <tr v-for="m in rows" :key="m.id">
            <td class="muted">{{ fmt(m.created_at) }}</td>
            <td><span class="badge" :class="{ teams: m.channel === 'teams' }">{{ m.channel }}</span></td>
            <td class="muted">{{ m.recipient }}</td>
            <td>{{ m.subject }}</td>
            <td class="muted">{{ m.type || '—' }}</td>
          </tr>
          <tr v-if="!rows.length"><td colspan="5" class="muted">Nothing sent yet. Run a cron command (quota:check, approvals:remind) or post a comment.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.badge.teams { background: #eae6fb; color: #5b3fb5; }
</style>
