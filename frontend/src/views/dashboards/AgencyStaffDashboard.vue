<script setup>
import { computed } from 'vue';
import { useRequestsQuery } from '@/composables/queries';
import { useAuthStore } from '@/stores/auth';
import StatCard from '@/components/StatCard.vue';
import AnalyticsGrid from '@/components/dashboard/AnalyticsGrid.vue';
import RecentRequests from '@/components/dashboard/RecentRequests.vue';

// The API already scopes /requests to "assigned to me" for non-manager staff.
const auth = useAuthStore();
const { data, isLoading } = useRequestsQuery();
const requests = computed(() => data.value ?? []);
const active = computed(() => requests.value.filter((r) => ['in_progress', 'revision'].includes(r.status)));
const recent = computed(() => [...requests.value].sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')));
</script>

<template>
  <section class="stack">
    <header>
      <h2>My work</h2>
      <p class="muted">
        {{ auth.isManager ? 'All agency work (manager view).' : 'Requests assigned to you.' }}
      </p>
    </header>

    <div class="row" style="flex-wrap: wrap;">
      <StatCard label="Assigned to me" :value="requests.length" />
      <StatCard label="Active" :value="active.length" />
      <StatCard label="In review" :value="requests.filter((r) => r.status === 'review').length" />
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>
    <template v-else-if="requests.length">
      <AnalyticsGrid :requests="requests" />
      <RecentRequests :requests="recent" :limit="6" />
    </template>
    <p v-else class="muted card" style="padding: 24px; text-align: center;">Nothing assigned to you yet.</p>
  </section>
</template>
