import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

import AppShell from '@/components/AppShell.vue';
import Login from '@/views/Login.vue';
import DashboardRouter from '@/views/DashboardRouter.vue';
import Requests from '@/views/Requests.vue';
import NewRequest from '@/views/NewRequest.vue';
import RequestDetail from '@/views/RequestDetail.vue';
import BrandHub from '@/views/BrandHub.vue';
import Notifications from '@/views/Notifications.vue';
import Projects from '@/views/Projects.vue';
import Tasks from '@/views/Tasks.vue';
import Campaigns from '@/views/Campaigns.vue';
import Reports from '@/views/Reports.vue';
import Privacy from '@/views/Privacy.vue';
import MetaHealth from '@/views/MetaHealth.vue';
import SetPassword from '@/views/SetPassword.vue';
import Analytics from '@/views/Analytics.vue';
import Admin from '@/views/Admin.vue';
import Publishing from '@/views/Publishing.vue';
import PublishingSettings from '@/views/PublishingSettings.vue';
import Settings from '@/views/Settings.vue';
import System from '@/views/System.vue';
import TeamOps from '@/views/TeamOps.vue';

const AGENCY = ['global_admin', 'agency_staff'];
const ALL = ['global_admin', 'agency_staff', 'client_owner', 'client_member'];

const routes = [
  { path: '/login', name: 'login', component: Login, meta: { public: true } },
  { path: '/set-password', name: 'set-password', component: SetPassword, meta: { public: true } },
  {
    path: '/',
    component: AppShell,
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: DashboardRouter, meta: { roles: ALL } },
      { path: 'requests', name: 'requests', component: Requests, meta: { roles: ALL } },
      { path: 'new-request', name: 'new-request', component: NewRequest, meta: { roles: ALL } },
      { path: 'requests/:id', name: 'request-detail', component: RequestDetail, meta: { roles: ALL } },
      { path: 'calendar', name: 'publishing', component: Publishing, meta: { roles: ALL } },
      { path: 'calendar/settings', name: 'publishing-settings', component: PublishingSettings, meta: { roles: ALL } },
      { path: 'analytics', name: 'analytics', component: Analytics, meta: { roles: ALL } },
      { path: 'brand', name: 'brand', component: BrandHub, meta: { roles: ALL } },
      { path: 'projects', name: 'projects', component: Projects, meta: { roles: ALL } },
      { path: 'tasks', name: 'tasks', component: Tasks, meta: { roles: ALL } },
      { path: 'campaigns', name: 'campaigns', component: Campaigns, meta: { roles: ALL } },
      { path: 'reports', name: 'reports', component: Reports, meta: { roles: ALL } },
      { path: 'privacy', name: 'privacy', component: Privacy, meta: { roles: ALL } },
      { path: 'meta', name: 'meta', component: MetaHealth, meta: { roles: ['client_owner'] } },
      { path: 'team-ops', name: 'team-ops', component: TeamOps, meta: { roles: AGENCY } },
      { path: 'admin', name: 'admin', component: Admin, meta: { roles: AGENCY } },
      { path: 'settings', name: 'settings', component: Settings, meta: { roles: ['global_admin'] } },
      { path: 'system', name: 'system', component: System, meta: { roles: ['global_admin'] } },
      { path: 'notifications', name: 'notifications', component: Notifications, meta: { roles: ALL } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Single global guard — UX gating only; the API enforces real access.
router.beforeEach((to) => {
  const auth = useAuthStore();

  if (to.meta.public) {
    return auth.isAuthenticated && to.name === 'login' ? { name: 'dashboard' } : true;
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } };
  }

  if (to.meta.roles && !to.meta.roles.includes(auth.role)) {
    return { name: 'dashboard' }; // unauthorized deep-link → role's default home
  }

  return true;
});

export default router;
