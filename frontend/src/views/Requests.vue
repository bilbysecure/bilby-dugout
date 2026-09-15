<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { useRequestsQuery, useSubscriptionsQuery } from '@/composables/queries';
import { AdminApi } from '@/api/endpoints';
import { SERVICE_TYPES } from '@/constants/options';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const { data: reqData, isLoading, isError } = useRequestsQuery();
const { data: subData } = useSubscriptionsQuery();
// team (for assignee avatars) — agency only
const { data: teamData } = useQuery({ queryKey: ['team-members'], queryFn: () => AdminApi.teamMembers(), enabled: auth.isAgency });

const requests = computed(() => reqData.value ?? []);
const subMap = computed(() => Object.fromEntries((subData.value ?? []).map((s) => [s.client_email, s])));
const memberMap = computed(() => Object.fromEntries((teamData.value ?? []).map((m) => [m.email, m])));

const clientName = (email) => subMap.value[email]?.company_name || subMap.value[email]?.client_name || email;

// ── Filters ───────────────────────────────────────────────────
const search = ref('');
const statusF = ref('');
const typeF = ref('');
const clientF = ref('');
const dueFrom = ref('');
const dueTo = ref('');

const REQUEST_STATUSES = ['pending_owner_approval', 'submitted', 'in_progress', 'review', 'revision', 'approved', 'scheduled', 'published', 'completed', 'cancelled'];

const clientOptions = computed(() => {
  const seen = new Map();
  for (const r of requests.value) if (!seen.has(r.client_email)) seen.set(r.client_email, clientName(r.client_email));
  return [...seen.entries()].map(([value, label]) => ({ value, label })).sort((a, b) => a.label.localeCompare(b.label));
});

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return requests.value.filter((r) => {
    if (q && !(`${r.title} ${r.submitted_by_name || ''}`.toLowerCase().includes(q))) return false;
    if (statusF.value && r.status !== statusF.value) return false;
    if (typeF.value && r.type !== typeF.value) return false;
    if (clientF.value && r.client_email !== clientF.value) return false;
    if (dueFrom.value && (!r.due_date || r.due_date < dueFrom.value)) return false;
    if (dueTo.value && (!r.due_date || r.due_date > dueTo.value)) return false;
    return true;
  });
});
const hasFilters = computed(() => search.value || statusF.value || typeF.value || clientF.value || dueFrom.value || dueTo.value);
function clearFilters() { search.value = statusF.value = typeF.value = clientF.value = dueFrom.value = dueTo.value = ''; }

// ── Display helpers ───────────────────────────────────────────
const TYPE_META = {
  social_media_post: { icon: '📱', tint: '#fdeef4' }, graphic_design: { icon: '🎨', tint: '#efeafe' },
  video_production: { icon: '🎬', tint: '#fdeaea' }, brand_asset: { icon: '🏷️', tint: '#e6f6f4' },
  marketing_campaign: { icon: '📣', tint: '#fff1e0' }, content_writing: { icon: '✍️', tint: '#eaf1fb' },
  website_update: { icon: '🌐', tint: '#e6f5fb' }, ads_campaign: { icon: '📊', tint: '#e9f6ea' },
  seo_content: { icon: '🔍', tint: '#eeeefc' }, print_design: { icon: '🖨️', tint: '#f2eee9' }, other: { icon: '📋', tint: '#eef0f3' },
};
const typeMeta = (t) => TYPE_META[t] || TYPE_META.other;

const STATUS_META = {
  pending_owner_approval: ['Pending approval', '#a15c00', '#fff4e0'], submitted: ['Submitted', '#2e5496', '#eaf1fb'],
  in_progress: ['In Progress', '#4b3fbb', '#eeecfb'], review: ['Client Review', '#7b3fb5', '#f4eafb'],
  revision: ['Revision', '#a15c00', '#fff4e0'], approved: ['Approved', '#0b7a68', '#e6f6f2'],
  scheduled: ['Scheduled', '#1b7a2e', '#e7f6ea'], published: ['Published', '#155d24', '#e7f6ea'],
  completed: ['Completed', '#3a6a44', '#eaf4ec'], cancelled: ['Cancelled', '#6b7480', '#eef0f3'],
};
const statusMeta = (s) => STATUS_META[s] || [s, '#6b7480', '#eef0f3'];
const PRIORITY_META = { urgent: ['Urgent', '#c0392b', '#fdece9'], high: ['High', '#a15c00', '#fff4e0'], normal: ['Normal', '#2e5496', '#eaf1fb'], low: ['Low', '#6b7480', '#eef0f3'] };
const priorityMeta = (p) => PRIORITY_META[p] || PRIORITY_META.normal;

const fmtDate = (d) => (d ? new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '');
const initials = (n) => (n || '?').split(' ').map((x) => x[0]).slice(0, 2).join('').toUpperCase();
const assignees = (r) => (r.assigned_to || []).map((e) => memberMap.value[e] || { email: e, full_name: e });

const open = (r) => router.push({ name: 'request-detail', params: { id: r.id } });
</script>

<template>
  <section class="stack">
    <header class="row" style="justify-content: space-between; align-items: flex-start;">
      <div>
        <h2>All Requests</h2>
        <p class="muted">{{ filtered.length }} of {{ requests.length }} request(s)</p>
      </div>
      <button v-if="auth.canCreateRequests" class="btn" @click="router.push({ name: 'new-request' })">＋ New Request</button>
    </header>

    <!-- Filter card -->
    <div class="filters card">
      <div class="filter-row">
        <div class="search">
          <span class="ico">🔍</span>
          <input v-model="search" class="input" placeholder="Search by title, requester" />
        </div>
        <select v-model="statusF" class="input">
          <option value="">All Statuses</option>
          <option v-for="s in REQUEST_STATUSES" :key="s" :value="s">{{ statusMeta(s)[0] }}</option>
        </select>
        <select v-model="typeF" class="input">
          <option value="">All Types</option>
          <option v-for="t in SERVICE_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
        <select v-model="clientF" class="input">
          <option value="">All Clients</option>
          <option v-for="c in clientOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
      </div>
      <div class="filter-row dates">
        <label class="stack"><span>Due date from</span><input v-model="dueFrom" class="input" type="date" /></label>
        <label class="stack"><span>Due date to</span><input v-model="dueTo" class="input" type="date" /></label>
        <button v-if="hasFilters" class="btn secondary clear" @click="clearFilters">Clear filters</button>
      </div>
    </div>

    <!-- Request cards -->
    <p v-if="isLoading" class="muted">Loading…</p>
    <p v-else-if="isError" class="muted">Could not load requests.</p>
    <p v-else-if="!filtered.length" class="muted card" style="text-align:center;padding:28px;">No requests match your filters.</p>
    <div v-else class="stack">
      <div v-for="r in filtered" :key="r.id" class="req card" @click="open(r)">
        <div class="req-icon" :style="{ background: typeMeta(r.type).tint }">{{ typeMeta(r.type).icon }}</div>
        <div class="req-body">
          <h3>{{ r.title }}</h3>
          <p class="desc muted">{{ r.description || 'No description.' }}</p>
          <div class="meta">
            <span class="meta-i">🏢 {{ clientName(r.client_email) }}</span>
            <span class="meta-i muted">👤 Created by {{ r.submitted_by_name || r.submitted_by_email || '—' }}</span>
          </div>
          <div class="tags">
            <span class="pill" :style="{ color: statusMeta(r.status)[1], background: statusMeta(r.status)[2] }">{{ statusMeta(r.status)[0] }}</span>
            <span class="pill" :style="{ color: priorityMeta(r.priority)[1], background: priorityMeta(r.priority)[2] }">{{ priorityMeta(r.priority)[0] }}</span>
            <span v-if="r.due_date" class="pill due">📅 {{ fmtDate(r.due_date) }}</span>
            <span class="avatars">
              <span v-for="(a, i) in assignees(r).slice(0, 3)" :key="i" class="av" :title="a.full_name">
                <img v-if="a.avatar_url" :src="a.avatar_url" alt="" />
                <span v-else>{{ initials(a.full_name) }}</span>
              </span>
              <span v-if="assignees(r).length > 3" class="av more">+{{ assignees(r).length - 3 }}</span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.filters { background: var(--surface); padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.filter-row { display: flex; gap: 12px; flex-wrap: wrap; }
.filter-row .input, .filter-row select { flex: 1; min-width: 150px; }
.search { position: relative; flex: 2; min-width: 220px; }
.search .ico { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); opacity: 0.5; font-size: 13px; }
.search .input { padding-left: 32px; width: 100%; }
.dates { align-items: flex-end; }
.dates .stack { flex: 0 0 auto; }
.dates label span { font-size: 12px; color: var(--muted); }
.dates .input { min-width: 160px; }
.clear { margin-left: auto; }

.req { display: flex; gap: 14px; cursor: pointer; transition: box-shadow .15s, border-color .15s; }
.req:hover { border-color: var(--primary); box-shadow: 0 2px 10px rgba(46,84,150,.08); }
.req-icon { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; flex: none; }
.req-body { flex: 1; min-width: 0; }
.req-body h3 { margin: 0 0 3px; font-size: 16px; }
.desc { margin: 0 0 8px; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.meta { display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; margin-bottom: 10px; }
.meta-i { display: inline-flex; align-items: center; gap: 5px; }
.tags { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.pill { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; }
.pill.due { color: var(--muted); background: #f2f4f7; font-weight: 500; }
.avatars { display: inline-flex; margin-left: 4px; }
.av { width: 26px; height: 26px; border-radius: 50%; background: #eef2f8; color: var(--primary); border: 2px solid var(--surface); margin-left: -8px; display: grid; place-items: center; font-size: 10px; font-weight: 700; overflow: hidden; }
.av:first-child { margin-left: 0; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.av.more { background: var(--primary); color: #fff; }
</style>
