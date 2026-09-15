<script setup>
import { ref, computed } from 'vue';
import { useRequestsQuery, useTeamQuery, usePostsQuery, useSubscriptionsQuery } from '@/composables/queries';
import StatCard from '@/components/StatCard.vue';

const tab = ref('workflow');
const tabs = [
  { key: 'workflow', label: 'Workflow' },
  { key: 'assignments', label: 'Task Assignment' },
  { key: 'sla', label: 'SLA Tracking' },
  { key: 'approvals', label: 'Approvals' },
  { key: 'performance', label: 'Performance' },
];

const { data: reqData, isLoading } = useRequestsQuery();
const { data: teamData } = useTeamQuery();
const { data: postData } = usePostsQuery();
const { data: subData } = useSubscriptionsQuery();

const requests = computed(() => reqData.value ?? []);
const team = computed(() => (teamData.value ?? []).filter((m) => m.status === 'active'));
const posts = computed(() => postData.value ?? []);
const slaByClient = computed(() => Object.fromEntries((subData.value ?? []).map((s) => [s.client_email, s.sla_tier])));

const ACTIVE = ['submitted', 'in_progress', 'review', 'revision', 'approved', 'scheduled'];
const DONE = ['completed', 'published'];
const WORKFLOW = ['submitted', 'in_progress', 'review', 'revision', 'approved', 'scheduled'];

const fmt = (s) => (s ? String(s).replaceAll('_', ' ') : '—');
const today = new Date(); today.setHours(0, 0, 0, 0);
const daysUntil = (d) => (d ? Math.ceil((new Date(d) - today) / 86400000) : null);

const active = computed(() => requests.value.filter((r) => ACTIVE.includes(r.status)));

// Workflow board
const byStatus = computed(() => {
  const g = {};
  for (const s of WORKFLOW) g[s] = active.value.filter((r) => r.status === s);
  return g;
});

// SLA rows
const slaRows = computed(() =>
  active.value
    .filter((r) => r.due_date)
    .map((r) => ({ ...r, days: daysUntil(r.due_date), tier: slaByClient.value[r.client_email] }))
    .sort((a, b) => a.days - b.days),
);
const slaState = (d) => (d < 0 ? 'breached' : d <= 2 ? 'at-risk' : 'on-track');
const overdueCount = computed(() => slaRows.value.filter((r) => r.days < 0).length);

// Approvals queue
const pendingPosts = computed(() => posts.value.filter((p) => p.status === 'pending_approval'));
const reviewReqs = computed(() => requests.value.filter((r) => ['review', 'revision'].includes(r.status)));

// Per-member performance / assignments
const perMember = computed(() =>
  team.value.map((m) => {
    const mine = requests.value.filter((r) => (r.assigned_to || []).includes(m.email));
    const act = mine.filter((r) => ACTIVE.includes(r.status));
    return {
      ...m,
      assigned: mine.length,
      active: act.length,
      completed: mine.filter((r) => DONE.includes(r.status)).length,
      overdue: act.filter((r) => r.due_date && daysUntil(r.due_date) < 0).length,
      items: act,
    };
  }),
);
</script>

<template>
  <section class="stack">
    <header>
      <h2>Team Ops</h2>
      <p class="muted">Internal workflow, task assignment, SLA tracking, approvals and performance for BilbyPixel staff.</p>
    </header>

    <div class="row" style="flex-wrap: wrap;">
      <StatCard label="Active work" :value="active.length" />
      <StatCard label="Overdue (SLA)" :value="overdueCount" />
      <StatCard label="Awaiting approval" :value="pendingPosts.length + reviewReqs.length" />
      <StatCard label="Active staff" :value="team.length" />
    </div>

    <div class="tabs">
      <button v-for="t in tabs" :key="t.key" class="tab" :class="{ active: tab === t.key }" @click="tab = t.key">{{ t.label }}</button>
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>

    <!-- Workflow board -->
    <div v-else-if="tab === 'workflow'" class="board">
      <div v-for="s in WORKFLOW" :key="s" class="col card">
        <div class="col-head"><span class="badge">{{ fmt(s) }}</span><span class="muted">{{ byStatus[s].length }}</span></div>
        <router-link v-for="r in byStatus[s]" :key="r.id" :to="{ name: 'request-detail', params: { id: r.id } }" class="mini">
          <strong>{{ r.title }}</strong>
          <span class="muted">{{ fmt(r.priority) }}</span>
        </router-link>
        <p v-if="!byStatus[s].length" class="muted empty">—</p>
      </div>
    </div>

    <!-- Task assignment -->
    <div v-else-if="tab === 'assignments'" class="stack">
      <div v-for="m in perMember" :key="m.id" class="card">
        <div class="row" style="justify-content: space-between;">
          <strong>{{ m.full_name }}</strong>
          <span class="muted">{{ m.active }} active · {{ m.overdue }} overdue</span>
        </div>
        <div class="assign-list">
          <router-link v-for="r in m.items" :key="r.id" :to="{ name: 'request-detail', params: { id: r.id } }" class="assign-chip" :class="{ late: r.due_date && daysUntil(r.due_date) < 0 }">
            {{ r.title }} <span class="badge">{{ fmt(r.status) }}</span>
          </router-link>
          <span v-if="!m.items.length" class="muted">No active tasks.</span>
        </div>
      </div>
      <p v-if="!perMember.length" class="muted">No staff found.</p>
    </div>

    <!-- SLA tracking -->
    <div v-else-if="tab === 'sla'" class="card">
      <table class="tbl">
        <thead><tr><th>Request</th><th>Client</th><th>SLA tier</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
          <tr v-for="r in slaRows" :key="r.id">
            <td><router-link :to="{ name: 'request-detail', params: { id: r.id } }">{{ r.title }}</router-link></td>
            <td class="muted">{{ r.client_email }}</td>
            <td><span class="badge">{{ fmt(r.tier) || '—' }}</span></td>
            <td class="muted">{{ r.due_date }}</td>
            <td>
              <span class="sla" :class="slaState(r.days)">
                {{ r.days < 0 ? `${-r.days}d overdue` : r.days === 0 ? 'due today' : `${r.days}d left` }}
              </span>
            </td>
          </tr>
          <tr v-if="!slaRows.length"><td colspan="5" class="muted">No dated active work.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Approvals -->
    <div v-else-if="tab === 'approvals'" class="stack">
      <div class="card">
        <strong>Posts awaiting approval ({{ pendingPosts.length }})</strong>
        <router-link v-for="p in pendingPosts" :key="p.id" :to="{ name: 'publishing' }" class="row line">
          <span>{{ p.title || p.caption || 'Untitled post' }}</span>
          <span class="badge" style="margin-left: auto;">pending approval</span>
        </router-link>
        <p v-if="!pendingPosts.length" class="muted">Nothing pending.</p>
      </div>
      <div class="card">
        <strong>Requests in review / revision ({{ reviewReqs.length }})</strong>
        <router-link v-for="r in reviewReqs" :key="r.id" :to="{ name: 'request-detail', params: { id: r.id } }" class="row line">
          <span>{{ r.title }}</span>
          <span class="badge" style="margin-left: auto;">{{ fmt(r.status) }}</span>
        </router-link>
        <p v-if="!reviewReqs.length" class="muted">Nothing to review.</p>
      </div>
    </div>

    <!-- Performance -->
    <div v-else class="card">
      <table class="tbl">
        <thead><tr><th>Staff</th><th>Designation</th><th>Assigned</th><th>Active</th><th>Completed</th><th>Overdue</th></tr></thead>
        <tbody>
          <tr v-for="m in perMember" :key="m.id">
            <td>{{ m.full_name }}</td>
            <td class="muted">{{ fmt(m.designation) }}</td>
            <td>{{ m.assigned }}</td>
            <td>{{ m.active }}</td>
            <td>{{ m.completed }}</td>
            <td :class="{ warn: m.overdue }">{{ m.overdue }}</td>
          </tr>
          <tr v-if="!perMember.length"><td colspan="6" class="muted">No staff found.</td></tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.tab { border: 1px solid var(--border); background: var(--surface); padding: 8px 14px; border-radius: var(--radius); }
.tab.active { border-color: var(--primary); background: #eef2f8; color: var(--primary); font-weight: 600; }
.board { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: start; }
.col { padding: 10px; }
.col-head { display: flex; justify-content: space-between; margin-bottom: 8px; }
.mini { display: flex; flex-direction: column; gap: 2px; background: var(--bg); border-radius: 6px; padding: 6px 8px; margin-bottom: 6px; }
.mini strong { font-size: 12px; }
.mini .muted { font-size: 11px; text-transform: capitalize; }
.empty { text-align: center; }
.assign-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.assign-chip { border: 1px solid var(--border); border-radius: 999px; padding: 4px 10px; font-size: 13px; display: inline-flex; gap: 6px; align-items: center; }
.assign-chip.late { border-color: var(--danger); }
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.sla { font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 999px; }
.sla.on-track { background: #e6f4ea; color: #1b5e20; }
.sla.at-risk { background: #fff4e0; color: #a15c00; }
.sla.breached { background: #fde8e6; color: var(--danger); }
.line { padding: 7px 0; border-bottom: 1px solid var(--border); }
.line:last-child { border-bottom: none; }
.warn { color: var(--danger); font-weight: 600; }
</style>
