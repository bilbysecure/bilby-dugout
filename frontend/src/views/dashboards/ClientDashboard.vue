<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery } from '@tanstack/vue-query';
import { useRequestsQuery } from '@/composables/queries';
import { AssistantApi, BillingApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';
import StatCard from '@/components/StatCard.vue';
import AnalyticsGrid from '@/components/dashboard/AnalyticsGrid.vue';
import RecentRequests from '@/components/dashboard/RecentRequests.vue';
import BillingPortalModal from '@/components/billing/BillingPortalModal.vue';

const auth = useAuthStore();
const router = useRouter();
const { data, isLoading } = useRequestsQuery();
const requests = computed(() => data.value ?? []);
const awaitingApproval = computed(() => requests.value.filter((r) => r.status === 'review'));
const isOwner = computed(() => auth.role === 'client_owner');
const recent = computed(() => [...requests.value].sort((a, b) => (b.created_at || '').localeCompare(a.created_at || '')));

// AI recommendations (mock provider)
const { data: recData } = useQuery({ queryKey: ['recommendations'], queryFn: () => AssistantApi.recommendations() });
const recs = computed(() => recData.value ?? []);

// Billing portal — simulated opens an in-app modal; a real Stripe URL opens in a new tab.
const showBilling = ref(false);
const billingBusy = ref(false);
async function manageBilling() {
  billingBusy.value = true;
  try {
    const res = await BillingApi.portalSession();
    if (res.simulated) showBilling.value = true;
    else window.open(res.url, '_blank');
  } finally {
    billingBusy.value = false;
  }
}
</script>

<template>
  <section class="stack">
    <header class="row" style="justify-content: space-between;">
      <div>
        <h2>Welcome{{ auth.user?.name ? `, ${auth.user.name.split(' ')[0]}` : '' }}</h2>
        <p class="muted">Your team's creative requests with BilbyPixel.</p>
      </div>
      <div class="row" style="gap: 8px;">
        <button v-if="isOwner" class="btn secondary" :disabled="billingBusy" @click="manageBilling">
          {{ billingBusy ? 'Opening…' : '💳 Manage billing' }}
        </button>
        <button class="btn" @click="router.push({ name: 'requests' })">View requests</button>
      </div>
    </header>

    <div class="row" style="flex-wrap: wrap;">
      <StatCard label="Total requests" :value="requests.length" />
      <StatCard label="In progress" :value="requests.filter((r) => r.status === 'in_progress').length" />
      <StatCard label="Awaiting your review" :value="awaitingApproval.length" />
    </div>

    <div v-if="isOwner && awaitingApproval.length" class="card">
      <strong>{{ awaitingApproval.length }} deliverable(s) need your approval.</strong>
    </div>

    <div v-if="recs.length" class="card stack">
      <strong>✨ Recommendations for you</strong>
      <div v-for="(r, i) in recs" :key="i" class="rec">
        <strong>{{ r.title }}</strong>
        <p class="muted" style="margin: 2px 0 0;">{{ r.detail }}</p>
      </div>
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>
    <template v-else-if="requests.length">
      <AnalyticsGrid :requests="requests" />
      <RecentRequests :requests="recent" :limit="6" />
    </template>
    <p v-else class="muted card" style="padding: 24px; text-align: center;">No requests yet — create your first one.</p>

    <BillingPortalModal v-if="showBilling" @close="showBilling = false" />
  </section>
</template>

<style scoped>
.rec { border-left: 3px solid var(--accent); padding-left: 10px; }
</style>
