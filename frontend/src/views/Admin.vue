<script setup>
import { ref, computed } from 'vue';
import { useInvoicesQuery } from '@/composables/queries';
import { useAuthStore } from '@/stores/auth';
import ClientsTab from '@/components/clients/ClientsTab.vue';
import FormConfigTab from '@/components/settings/FormConfigTab.vue';
import ServicePlansTab from '@/components/settings/ServicePlansTab.vue';
import SlaTiersTab from '@/components/settings/SlaTiersTab.vue';

const auth = useAuthStore();
const isAdmin = computed(() => auth.role === 'global_admin');

const tab = ref('clients');
const tabs = computed(() => [
  { key: 'clients', label: 'Clients' },
  { key: 'invoices', label: 'Invoices' },
  ...(isAdmin.value ? [
    { key: 'form-config', label: 'Form Config' },
    { key: 'service-plans', label: 'Service Plans' },
    { key: 'sla-tiers', label: 'SLA Tiers' },
  ] : []),
]);

const { data: invData, isLoading: invLoading } = useInvoicesQuery();
const invoiceRows = computed(() => invData.value ?? []);

const fmt = (s) => (s ? String(s).replaceAll('_', ' ') : '—');
const money = (a, c) => (a == null ? '—' : `${c || 'AUD'} ${Number(a).toFixed(2)}`);
</script>

<template>
  <section class="stack">
    <header>
      <h2>Admin</h2>
      <p class="muted">Manage client accounts, subscriptions and quotas.</p>
    </header>

    <div class="tabs">
      <button
        v-for="t in tabs" :key="t.key"
        class="tab" :class="{ active: tab === t.key }"
        @click="tab = t.key"
      >{{ t.label }}</button>
    </div>

    <!-- Clients -->
    <ClientsTab v-if="tab === 'clients'" />

    <!-- Invoices -->
    <div v-else-if="tab === 'invoices'" class="card">
      <p v-if="invLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Status</th><th>Due</th></tr></thead>
        <tbody>
          <tr v-for="inv in invoiceRows" :key="inv.id">
            <td>{{ inv.invoice_number || `#${inv.id}` }}</td>
            <td>{{ inv.company_name || inv.client_email }}</td>
            <td>{{ money(inv.amount, inv.currency) }}</td>
            <td><span class="badge">{{ fmt(inv.status) }}</span></td>
            <td class="muted">{{ inv.due_date || '—' }}</td>
          </tr>
          <tr v-if="!invoiceRows.length"><td colspan="5" class="muted">No invoices.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Form config / Service plans / SLA tiers (global admin) -->
    <FormConfigTab v-else-if="tab === 'form-config'" />
    <ServicePlansTab v-else-if="tab === 'service-plans'" />
    <SlaTiersTab v-else-if="tab === 'sla-tiers'" />
  </section>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.tab { border: 1px solid var(--border); background: var(--surface); padding: 7px 14px; border-radius: var(--radius); }
.tab.active { border-color: var(--primary); background: #eef2f8; color: var(--primary); font-weight: 600; }
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.03em; }
tr:last-child td { border-bottom: none; }
</style>
