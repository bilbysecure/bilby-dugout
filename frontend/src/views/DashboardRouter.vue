<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';
import GlobalAdminDashboard from './dashboards/GlobalAdminDashboard.vue';
import AgencyStaffDashboard from './dashboards/AgencyStaffDashboard.vue';
import ClientDashboard from './dashboards/ClientDashboard.vue';

// Single landing route → the right dashboard for the principal's role (architecture §10).
const auth = useAuthStore();
const dashboard = computed(() => {
  switch (auth.role) {
    case 'global_admin': return GlobalAdminDashboard;
    case 'agency_staff': return AgencyStaffDashboard;
    default: return ClientDashboard; // client_owner / client_member
  }
});
</script>

<template>
  <component :is="dashboard" />
</template>
