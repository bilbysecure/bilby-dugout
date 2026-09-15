<script setup>
import { ref } from 'vue';
import TeamMembersTab from '@/components/settings/TeamMembersTab.vue';
import DesignationsTab from '@/components/settings/DesignationsTab.vue';
import RolesTab from '@/components/settings/RolesTab.vue';
import OutboxTab from '@/components/settings/OutboxTab.vue';
import { StaffRolesApi, ClientRolesApi } from '@/api/endpoints';
import { STAFF_PERMISSIONS, CLIENT_PERMISSIONS } from '@/constants/permissions';

const tab = ref('team');
const tabs = [
  { key: 'team', label: 'Team Members' },
  { key: 'designations', label: 'Designations' },
  { key: 'staff-roles', label: 'Staff Roles' },
  { key: 'client-roles', label: 'Client Roles' },
  { key: 'outbox', label: 'Email Outbox' },
];
</script>

<template>
  <section class="stack">
    <header>
      <h2>Settings</h2>
      <p class="muted">Manage BilbyPixel staff, designations, and reusable access roles.</p>
    </header>

    <div class="tabs">
      <button v-for="t in tabs" :key="t.key" class="tab" :class="{ active: tab === t.key }" @click="tab = t.key">
        {{ t.label }}
      </button>
    </div>

    <TeamMembersTab v-if="tab === 'team'" />
    <DesignationsTab v-else-if="tab === 'designations'" />
    <RolesTab
      v-else-if="tab === 'staff-roles'"
      kind="staff" queryKey="staff-roles" :api="StaffRolesApi" :groups="STAFF_PERMISSIONS"
      title="Staff role" hint="e.g. BilbyPixel Team Admin, Team Leader, Team Member"
    />
    <RolesTab
      v-else-if="tab === 'client-roles'"
      kind="client" queryKey="client-roles" :api="ClientRolesApi" :groups="CLIENT_PERMISSIONS"
      title="Client role" hint="Roles for client owners and their team members"
    />
    <OutboxTab v-else />
  </section>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.tab { border: 1px solid var(--border); background: var(--surface); padding: 8px 15px; border-radius: var(--radius); }
.tab.active { border-color: var(--primary); background: #eef2f8; color: var(--primary); font-weight: 600; }
</style>
