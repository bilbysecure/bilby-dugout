<script setup>
import { ref, computed } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { SubscriptionsApi, AdminApi } from '@/api/endpoints';
import { useRequestsQuery } from '@/composables/queries';
import ClientTeamDrawer from '@/components/clients/ClientTeamDrawer.vue';
import ClientEditDrawer from '@/components/clients/ClientEditDrawer.vue';

const qc = useQueryClient();
const { data: subData, isLoading } = useQuery({ queryKey: ['subscriptions'], queryFn: () => SubscriptionsApi.list() });
const { data: reqData } = useRequestsQuery();
const { data: staffData } = useQuery({ queryKey: ['team-members'], queryFn: () => AdminApi.teamMembers() });

const clients = computed(() => subData.value ?? []);
const requests = computed(() => reqData.value ?? []);
const staff = computed(() => (staffData.value ?? []).filter((s) => s.status === 'active'));
const staffByEmail = computed(() => Object.fromEntries((staffData.value ?? []).map((s) => [s.email, s])));

const ACTIVE = ['submitted', 'in_progress', 'review', 'revision', 'approved', 'scheduled'];
const now = new Date();
const activeReqs = (email) => requests.value.filter((r) => r.client_email === email && ACTIVE.includes(r.status)).length;
const monthlyUsed = (email) => requests.value.filter((r) => {
  if (r.client_email !== email || !r.created_at) return false;
  const d = new Date(String(r.created_at).replace(' ', 'T'));
  return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
}).length;

const search = ref('');
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return clients.value.filter((c) => !q || `${c.client_name} ${c.company_name} ${c.client_email}`.toLowerCase().includes(q));
});
const monthLabel = now.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

const initial = (n) => (n || '?').trim().charAt(0).toUpperCase();
const fmtDate = (d) => (d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—');

// popovers / menus
const openMenu = ref(null);
const openPicker = ref(null);
function closeAll() { openMenu.value = null; openPicker.value = null; }

// drawers
const teamClient = ref(null);
const editClient = ref(null);

async function toggleManager(sub, s) {
  const list = sub.account_managers || [];
  const has = list.some((m) => m.email === s.email);
  const managers = has ? list.filter((m) => m.email !== s.email) : [...list, { email: s.email, name: s.full_name }];
  await SubscriptionsApi.update(sub.id, { account_managers: managers });
  qc.invalidateQueries({ queryKey: ['subscriptions'] });
}
const isManagerOn = (sub, email) => (sub.account_managers || []).some((m) => m.email === email);
</script>

<template>
  <section class="stack">
    <div class="row" style="justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
      <div>
        <p class="muted" style="margin: 0;">{{ monthLabel }} · {{ clients.length }} client{{ clients.length === 1 ? '' : 's' }}</p>
      </div>
      <div class="search"><span class="ico">🔍</span><input v-model="search" class="input" placeholder="Search client…" /></div>
    </div>

    <div class="card table-card">
      <p v-if="isLoading" class="muted" style="padding: 16px;">Loading…</p>
      <table v-else class="tbl">
        <thead>
          <tr><th>Client</th><th>Plan</th><th>Status</th><th>Monthly Quota</th><th>Active Reqs</th><th>Account Manager</th><th>Renewal</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="c in filtered" :key="c.id">
            <td>
              <div class="client">
                <span class="avatar"><img v-if="c.avatar_url" :src="c.avatar_url" alt="" /><span v-else>{{ initial(c.company_name || c.client_name) }}</span></span>
                <div>
                  <strong>{{ c.client_name || c.company_name }}</strong>
                  <div class="co">{{ c.company_name }}</div>
                  <div class="muted em">{{ c.client_email }}</div>
                </div>
              </div>
            </td>
            <td>{{ c.plan_name || '—' }}</td>
            <td><span class="badge" :class="c.status === 'active' ? 'ok' : 'off'">✓ {{ c.status }}</span></td>
            <td>
              <template v-if="c.monthly_request_limit > 0">
                <div class="quota">
                  <div class="qbar"><div class="qfill" :style="{ width: `${Math.min(100, (monthlyUsed(c.client_email) / c.monthly_request_limit) * 100)}%` }" /></div>
                  <span class="qnum">{{ monthlyUsed(c.client_email) }} / {{ c.monthly_request_limit }}</span>
                </div>
                <div class="muted qsub">{{ monthlyUsed(c.client_email) }} used this month</div>
              </template>
              <span v-else class="muted">Unlimited</span>
            </td>
            <td><span class="reqs">{{ activeReqs(c.client_email) }}</span></td>
            <td>
              <div class="managers">
                <span v-for="m in (c.account_managers || []).slice(0, 3)" :key="m.email" class="mav" :title="m.name">
                  <img v-if="staffByEmail[m.email]?.avatar_url" :src="staffByEmail[m.email].avatar_url" alt="" />
                  <span v-else>{{ initial(m.name) }}</span>
                </span>
                <div class="pick-wrap">
                  <button class="add-m" @click="openPicker = openPicker === c.id ? null : c.id; openMenu = null">＋</button>
                  <div v-if="openPicker === c.id" class="popover">
                    <div class="pop-title">Account managers</div>
                    <label v-for="s in staff" :key="s.email" class="pop-row">
                      <input type="checkbox" :checked="isManagerOn(c, s.email)" @change="toggleManager(c, s)" />
                      {{ s.full_name }}
                    </label>
                    <p v-if="!staff.length" class="muted" style="padding: 6px;">No staff.</p>
                  </div>
                </div>
              </div>
            </td>
            <td class="muted">{{ fmtDate(c.renewal_date) }}</td>
            <td class="dots-cell">
              <button class="dots" @click="openMenu = openMenu === c.id ? null : c.id; openPicker = null">⋯</button>
              <div v-if="openMenu === c.id" class="menu">
                <button @click="teamClient = c; closeAll()">👥 Team</button>
                <button @click="editClient = c; closeAll()">✎ Edit Client</button>
              </div>
            </td>
          </tr>
          <tr v-if="!filtered.length"><td colspan="8" class="muted" style="text-align: center; padding: 20px;">No clients found.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- click-away backdrop for menus -->
    <div v-if="openMenu || openPicker" class="backdrop" @click="closeAll" />

    <ClientTeamDrawer v-if="teamClient" :client="teamClient" @close="teamClient = null" />
    <ClientEditDrawer v-if="editClient" :client="editClient" @close="editClient = null" />
  </section>
</template>

<style scoped>
.search { position: relative; min-width: 260px; }
.search .ico { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); opacity: .5; }
.search .input { padding-left: 32px; }
.table-card { padding: 0; overflow: visible; }
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
tr:last-child td { border-bottom: none; }
.client { display: flex; gap: 10px; align-items: center; }
.avatar { width: 38px; height: 38px; border-radius: 50%; background: #eef2f8; color: var(--primary); display: grid; place-items: center; font-weight: 700; overflow: hidden; flex: none; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.co { color: var(--primary); font-size: 13px; }
.em { font-size: 12px; }
.badge.ok { background: #e6f6f2; color: #0b7a68; }
.badge.off { background: #eee; color: var(--muted); }
.quota { display: flex; align-items: center; gap: 8px; }
.qbar { width: 70px; height: 6px; background: var(--bg); border-radius: 999px; overflow: hidden; }
.qfill { height: 100%; background: var(--primary); }
.qnum { font-size: 12px; white-space: nowrap; }
.qsub { font-size: 11px; }
.reqs { color: var(--primary); font-weight: 700; }
.managers { display: flex; align-items: center; }
.mav { width: 28px; height: 28px; border-radius: 50%; background: #eef2f8; color: var(--primary); border: 2px solid var(--surface); margin-left: -8px; display: grid; place-items: center; font-size: 11px; font-weight: 700; overflow: hidden; }
.mav:first-child { margin-left: 0; }
.mav img { width: 100%; height: 100%; object-fit: cover; }
.pick-wrap { position: relative; }
.add-m { width: 28px; height: 28px; border-radius: 50%; border: 1px dashed var(--border); background: var(--surface); color: var(--muted); margin-left: 4px; cursor: pointer; }
.popover { position: absolute; top: 34px; left: 0; z-index: 30; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 6px 20px rgba(0,0,0,.12); padding: 8px; min-width: 200px; }
.pop-title { font-size: 11px; color: var(--muted); text-transform: uppercase; padding: 2px 6px 6px; }
.pop-row { display: flex; align-items: center; gap: 8px; padding: 6px; font-size: 14px; cursor: pointer; border-radius: 6px; }
.pop-row:hover { background: var(--bg); }
.dots-cell { position: relative; }
.dots { border: none; background: none; font-size: 18px; color: var(--muted); cursor: pointer; }
.menu { position: absolute; right: 14px; top: 40px; z-index: 30; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 6px 20px rgba(0,0,0,.12); padding: 6px; min-width: 160px; }
.menu button { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; border: none; background: none; padding: 8px 10px; border-radius: 6px; cursor: pointer; font-size: 14px; }
.menu button:hover { background: var(--bg); }
.backdrop { position: fixed; inset: 0; z-index: 20; }
</style>
