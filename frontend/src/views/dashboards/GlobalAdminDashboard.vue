<script setup>
import { computed } from 'vue';
import { useRequestsQuery } from '@/composables/queries';
import StatCard from '@/components/StatCard.vue';
import AnalyticsGrid from '@/components/dashboard/AnalyticsGrid.vue';
import RecentRequests from '@/components/dashboard/RecentRequests.vue';

const { data, isLoading } = useRequestsQuery();
const requests = computed(() => data.value ?? []);
const count = (s) => requests.value.filter((r) => r.status === s).length;
const recent = computed(() => [...requests.value].sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')));
</script>

<template>
  <section class="stack">
    <header>
      <h2>Agency overview</h2>
      <p class="muted">All clients and requests across BilbyPixel.</p>
    </header>

    <div class="row" style="flex-wrap: wrap;">
      <StatCard label="Total requests" :value="requests.length" />
      <StatCard label="In progress" :value="count('in_progress')" />
      <StatCard label="Client review" :value="count('review')" />
      <StatCard label="Completed" :value="count('completed')" />
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>
    <template v-else>
      <AnalyticsGrid :requests="requests" />
      <RecentRequests :requests="recent" :limit="6" />
    </template>
  </section>
</template>
