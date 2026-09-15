<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useQueryClient } from '@tanstack/vue-query';
import NotificationBell from '@/components/NotificationBell.vue';
import BilbyLogo from '@/components/BilbyLogo.vue';

const auth = useAuthStore();
const router = useRouter();
const qc = useQueryClient();
function exitImpersonation() { auth.stopImpersonating(); qc.invalidateQueries(); router.push({ name: 'admin' }); }

// Line icons (inner SVG markup, stroked with currentColor).
const ICONS = {
  dashboard: '<rect x="3" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6"/>',
  requests: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/>',
  calendar: '<rect x="3" y="4.5" width="18" height="16" rx="2.2"/><path d="M3 9.5h18M8 3v3M16 3v3"/>',
  brand: '<path d="M12 3a9 9 0 1 0 0 18c.9 0 1.5-.7 1.5-1.5 0-.5-.2-.8-.5-1.1-.3-.3-.5-.7-.5-1.1 0-.8.6-1.3 1.4-1.3H15a5 5 0 0 0 5-5c0-4.4-3.6-8-8-8z"/><circle cx="7.5" cy="10.5" r="1.1"/><circle cx="12" cy="7.5" r="1.1"/><circle cx="16.5" cy="10.5" r="1.1"/>',
  analytics: '<path d="M3 21h18"/><path d="M6 21v-7"/><path d="M12 21V5"/><path d="M18 21v-11"/>',
  teamops: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7.5" r="3.5"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M15.5 4.13a4 4 0 0 1 0 7.5"/>',
  admin: '<rect x="3" y="7" width="18" height="13" rx="2.2"/><path d="M8 7V5.5A2.5 2.5 0 0 1 10.5 3h3A2.5 2.5 0 0 1 16 5.5V7M3 12.5h18"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .32 1.77l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.6 1.6 0 0 0-1.77-.32 1.6 1.6 0 0 0-1 1.47V21a2 2 0 0 1-4 0v-.1A1.6 1.6 0 0 0 8.4 19.4a1.6 1.6 0 0 0-1.77.32l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.6 1.6 0 0 0 4.6 15a1.6 1.6 0 0 0-1.47-1H3a2 2 0 0 1 0-4h.1A1.6 1.6 0 0 0 4.6 8.4a1.6 1.6 0 0 0-.32-1.77l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.6 1.6 0 0 0 9 4.6a1.6 1.6 0 0 0 1-1.47V3a2 2 0 0 1 4 0v.1A1.6 1.6 0 0 0 15 4.6a1.6 1.6 0 0 0 1.77-.32l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.6 1.6 0 0 0 19.4 9v.06a1.6 1.6 0 0 0 1.47 1H21a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
  system: '<rect x="3" y="4" width="18" height="8" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 8h.01M7 17h.01"/>',
  projects: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/><path d="M9 13h6"/>',
  tasks: '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
  campaigns: '<path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
  reports: '<path d="M6 3h9l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v6h6M9 14l2 2 4-4"/>',
  privacy: '<path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9.5 12l2 2 3.5-4"/>',
  meta: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
  chevron: '<path d="M15 18l-6-6 6-6"/>',
};

// Grouped nav, gated by role + optional predicate. The API is the real gate.
const navGroups = [
  { label: '', items: [
    { to: { name: 'dashboard' }, label: 'Dashboard', icon: ICONS.dashboard },
    { to: { name: 'requests' }, label: 'All Requests', icon: ICONS.requests },
    { to: { name: 'publishing' }, label: 'Content Calendar', icon: ICONS.calendar },
    { to: { name: 'brand' }, label: 'Brand Hub', icon: ICONS.brand },
    { to: { name: 'projects' }, label: 'Projects', icon: ICONS.projects },
    { to: { name: 'tasks' }, label: 'Tasks', icon: ICONS.tasks },
    { to: { name: 'campaigns' }, label: 'Ad Campaigns', icon: ICONS.campaigns },
    { to: { name: 'reports' }, label: 'Reports', icon: ICONS.reports },
    { to: { name: 'privacy' }, label: 'Privacy & Data', icon: ICONS.privacy, when: () => auth.role === 'client_owner' },
    { to: { name: 'meta' }, label: 'Meta Integration', icon: ICONS.meta, when: () => auth.role === 'client_owner' },
  ] },
  { label: 'Team', items: [
    { to: { name: 'analytics' }, label: 'Analytics', icon: ICONS.analytics },
    { to: { name: 'team-ops' }, label: 'Team Ops', icon: ICONS.teamops, when: () => auth.isAgency },
    { to: { name: 'admin' }, label: 'Admin', icon: ICONS.admin, when: () => auth.isAgency },
    { to: { name: 'settings' }, label: 'Settings', icon: ICONS.settings, when: () => auth.role === 'global_admin' },
    { to: { name: 'system' }, label: 'System', icon: ICONS.system, when: () => auth.role === 'global_admin' },
  ] },
];

const visibleGroups = computed(() =>
  navGroups
    .map((g) => ({ ...g, items: g.items.filter((i) => !i.when || i.when()) }))
    .filter((g) => g.items.length),
);
const roleLabel = computed(() => ({
  global_admin: 'Global Admin',
  agency_staff: 'BilbyPixel team',
  client_owner: 'Client Owner',
  client_member: 'Client Member',
}[auth.role] ?? auth.role));
const displayName = computed(() => (auth.impersonation ? auth.impersonation.label : auth.user?.name));
const initial = computed(() => (displayName.value || '?').trim().charAt(0).toUpperCase());

// Collapse (icon-only rail), remembered across reloads.
const collapsed = ref(localStorage.getItem('bd_sidebar_collapsed') === '1');
function toggleCollapse() {
  collapsed.value = !collapsed.value;
  localStorage.setItem('bd_sidebar_collapsed', collapsed.value ? '1' : '0');
}

async function logout() {
  await auth.logout();
  router.replace({ name: 'login' });
}
</script>

<template>
  <div class="shell" :class="{ collapsed }">
    <aside class="sidebar">
      <!-- Brand -->
      <div class="brand">
        <BilbyLogo :size="36" />
        <div v-if="!collapsed" class="brand-text">
          <strong>BilbyDugout</strong>
          <span class="subtitle">Client Request Portal</span>
        </div>
      </div>

      <!-- Nav -->
      <nav class="nav">
        <div v-for="(g, gi) in visibleGroups" :key="gi" class="nav-group" :class="{ divided: gi > 0 }">
          <div v-if="g.label && !collapsed" class="nav-group-label">{{ g.label }}</div>
          <router-link
            v-for="item in g.items"
            :key="item.label"
            :to="item.to"
            class="nav-link"
            active-class="active"
            :title="collapsed ? item.label : null"
          >
            <svg class="ico" viewBox="0 0 24 24" v-html="item.icon" />
            <span v-if="!collapsed" class="nav-label">{{ item.label }}</span>
          </router-link>
        </div>
      </nav>

      <!-- User card -->
      <div class="user">
        <div class="user-top">
          <span class="avatar">{{ initial }}</span>
          <div v-if="!collapsed" class="user-info">
            <strong>{{ displayName }}</strong>
            <span class="user-role">{{ roleLabel }}</span>
            <span class="user-email">{{ auth.user?.email }}</span>
          </div>
          <NotificationBell v-if="!collapsed" class="user-bell" />
        </div>
        <button class="logout" :title="collapsed ? 'Log out' : null" @click="logout">
          <svg class="ico" viewBox="0 0 24 24" v-html="ICONS.logout" />
          <span v-if="!collapsed">Log out</span>
        </button>
      </div>

      <!-- Collapse toggle -->
      <button class="collapse-btn" @click="toggleCollapse" :title="collapsed ? 'Expand' : 'Collapse'">
        <svg class="ico chev" :class="{ flip: collapsed }" viewBox="0 0 24 24" v-html="ICONS.chevron" />
        <span v-if="!collapsed">Collapse</span>
      </button>
    </aside>

    <main class="content">
      <div v-if="auth.impersonation" class="imp-banner">
        👁 Viewing as <strong>{{ auth.impersonation.label }}</strong>
        <button class="imp-exit" @click="exitImpersonation">Exit</button>
      </div>
      <router-view />
    </main>
  </div>
</template>

<style scoped>
/* ── Crystal theme · frosted light sidebar (scoped) ─────────────────────── */
.shell {
  --sb-item: #4C4666;
  --sb-item-hover-bg: rgba(108, 74, 182, 0.07);
  --sb-active-bg: linear-gradient(145deg, rgba(108, 74, 182, 0.16), rgba(108, 74, 182, 0.08));
  --sb-active-icon: var(--primary);
  --sb-label: var(--muted);
  --sb-divider: var(--line);
  --sb-panel: var(--glass-2);
  display: grid;
  grid-template-columns: 248px 1fr;
  min-height: 100vh;
}
.shell.collapsed { grid-template-columns: 88px 1fr; }

.sidebar {
  margin: 12px 0 12px 12px;
  border-radius: 20px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.74), rgba(255, 255, 255, 0.5));
  backdrop-filter: blur(22px);
  -webkit-backdrop-filter: blur(22px);
  border: 1px solid var(--ring);
  box-shadow: var(--shadow);
  color: var(--sb-item);
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 12px;
  height: calc(100vh - 24px);
}

/* Brand */
.brand { display: flex; align-items: center; gap: 12px; padding: 4px 6px 14px; border-bottom: 1px solid var(--sb-divider); }
.brand-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.brand-text strong { color: var(--primary-deep); font-size: 16px; font-weight: 700; letter-spacing: 0.01em; }
.subtitle { color: var(--muted); font-size: 10px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; margin-top: 3px; }
.shell.collapsed .brand { justify-content: center; padding: 4px 0 14px; }

/* Nav */
.nav { display: flex; flex-direction: column; gap: 5px; flex: 1; overflow-y: auto; }
.nav-group { display: flex; flex-direction: column; gap: 3px; padding-top: 4px; }
.nav-group.divided { margin-top: 8px; padding-top: 14px; border-top: 1px solid var(--sb-divider); }
.nav-group-label { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--sb-label); padding: 2px 12px 6px; }

.nav-link {
  display: flex; align-items: center; gap: 12px;
  padding: 9px 11px; border-radius: 12px;
  color: var(--sb-item); font-size: 14.5px; font-weight: 500;
  border: 1px solid transparent;
  transition: background 0.12s, color 0.12s;
}
.nav-link:hover { background: var(--sb-item-hover-bg); color: var(--primary-deep); }
.nav-link.active { background: var(--sb-active-bg); color: var(--primary-deep); font-weight: 600; border-color: rgba(108, 74, 182, 0.2); }
.nav-link.active .ico { color: var(--sb-active-icon); }
.shell.collapsed .nav-link { justify-content: center; padding: 11px 0; }

/* Icons (stroked with currentColor) */
.ico { width: 20px; height: 20px; flex: none; color: inherit; opacity: 0.85; }
.ico :deep(*) { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.nav-link.active .ico { opacity: 1; }

/* User card */
.user { border-top: 1px solid var(--sb-divider); padding-top: 14px; display: flex; flex-direction: column; gap: 10px; }
.user-top { display: flex; align-items: center; gap: 10px; background: var(--sb-panel); border: 1px solid var(--ring); border-radius: 14px; padding: 10px; }
.avatar { width: 36px; height: 36px; border-radius: 11px; background: linear-gradient(145deg, var(--primary-2), var(--primary)); color: #fff; display: grid; place-items: center; font-weight: 700; flex: none; }
.user-info { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; flex: 1; }
.user-info strong { color: var(--text); font-size: 14px; font-weight: 600; }
.user-role { color: var(--muted); font-size: 12px; }
.user-email { color: var(--muted); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-bell { color: var(--muted); }
.logout {
  display: flex; align-items: center; gap: 10px;
  background: none; border: none; cursor: pointer;
  color: var(--sb-item); font-size: 14px; padding: 8px 12px; border-radius: 10px;
}
.logout:hover { color: var(--primary-deep); background: var(--sb-item-hover-bg); }
.shell.collapsed .user-top { justify-content: center; padding: 8px; }
.shell.collapsed .logout { justify-content: center; }

/* Collapse */
.collapse-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  background: none; border: none; cursor: pointer;
  color: var(--sb-label); font-size: 13px; padding: 10px; border-radius: 10px;
}
.collapse-btn:hover { color: var(--primary-deep); background: var(--sb-item-hover-bg); }
.chev { width: 16px; height: 16px; transition: transform 0.15s; }
.chev.flip { transform: rotate(180deg); }

/* Content */
.content { padding: 26px 30px; max-width: 1180px; }
.imp-banner { background: var(--gold-t); color: #9A6B18; border: 1px solid rgba(227, 185, 107, 0.5); border-radius: 14px; padding: 9px 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-size: 14px; }
.imp-exit { margin-left: auto; border: 1px solid #9A6B18; background: transparent; color: #9A6B18; border-radius: 8px; padding: 3px 12px; cursor: pointer; font-size: 13px; }
</style>
