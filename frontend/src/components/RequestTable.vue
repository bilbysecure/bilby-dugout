<script setup>
defineProps({
  requests: { type: Array, default: () => [] },
  empty: { type: String, default: 'No requests yet.' },
});

const fmt = (s) => (s ? String(s).replaceAll('_', ' ') : '—');
</script>

<template>
  <div class="card">
    <p v-if="!requests.length" class="muted">{{ empty }}</p>
    <table v-else class="tbl">
      <thead>
        <tr><th>Title</th><th>Type</th><th>Status</th><th>Priority</th><th>Due</th></tr>
      </thead>
      <tbody>
        <tr v-for="r in requests" :key="r.id">
          <td>
            <router-link :to="{ name: 'request-detail', params: { id: r.id } }">
              {{ r.title }}
            </router-link>
          </td>
          <td class="muted">{{ fmt(r.type) }}</td>
          <td><span class="badge">{{ fmt(r.status) }}</span></td>
          <td class="muted">{{ fmt(r.priority) }}</td>
          <td class="muted">{{ r.due_date || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.03em; }
tr:last-child td { border-bottom: none; }
</style>
