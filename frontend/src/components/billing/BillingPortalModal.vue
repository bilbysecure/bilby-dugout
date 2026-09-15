<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { AdminApi } from '@/api/endpoints';

defineEmits(['close']);

const { data: subData } = useQuery({ queryKey: ['subscriptions'], queryFn: () => AdminApi.subscriptions() });
const { data: invData, isLoading } = useQuery({ queryKey: ['invoices'], queryFn: () => AdminApi.invoices() });

const sub = computed(() => (subData.value ?? [])[0] || null);
const invoices = computed(() => invData.value ?? []);
const money = (a, c) => (a == null ? '—' : `${c || 'AUD'} ${Number(a).toFixed(2)}`);
const fmt = (s) => (s ? String(s).replaceAll('_', ' ') : '—');
</script>

<template>
  <div class="overlay" @click.self="$emit('close')">
    <div class="portal card">
      <div class="row" style="justify-content: space-between; align-items: flex-start;">
        <div class="row" style="gap: 10px; align-items: center;">
          <span class="stripe">S</span>
          <div>
            <h3 style="margin: 0;">Billing portal</h3>
            <span class="muted" style="font-size: 12px;">{{ sub?.company_name || sub?.client_name || 'Your account' }}</span>
          </div>
        </div>
        <button class="icon-btn" @click="$emit('close')">✕</button>
      </div>

      <div class="sim-banner">Simulated portal — in production this opens Stripe's hosted billing portal.</div>

      <!-- Current plan -->
      <div class="block">
        <div class="block-title">Current plan</div>
        <div v-if="sub" class="plan">
          <div>
            <strong>{{ sub.plan_name || 'Plan' }}</strong>
            <div class="muted" style="font-size: 13px;">
              {{ fmt(sub.sla_tier) }} SLA ·
              {{ sub.monthly_request_limit ? sub.monthly_request_limit + ' requests/mo' : 'Unlimited requests' }}
            </div>
          </div>
          <span class="badge" :class="{ off: sub.status !== 'active' }">{{ sub.status }}</span>
        </div>
        <p v-else class="muted">No active subscription on file.</p>
        <div v-if="sub?.renewal_date" class="muted" style="font-size: 13px; margin-top: 6px;">Renews {{ sub.renewal_date }}</div>
      </div>

      <!-- Payment method -->
      <div class="block">
        <div class="block-title">Payment method</div>
        <div class="row" style="gap: 10px;">
          <span class="card-chip">VISA</span>
          <span>•••• •••• •••• 4242</span>
          <button class="link" disabled>Update</button>
        </div>
      </div>

      <!-- Invoices -->
      <div class="block">
        <div class="block-title">Invoice history</div>
        <p v-if="isLoading" class="muted">Loading…</p>
        <table v-else-if="invoices.length" class="tbl">
          <thead><tr><th>Invoice</th><th>Amount</th><th>Status</th><th>Due</th><th></th></tr></thead>
          <tbody>
            <tr v-for="inv in invoices" :key="inv.id">
              <td>{{ inv.invoice_number || `#${inv.id}` }}</td>
              <td>{{ money(inv.amount, inv.currency) }}</td>
              <td><span class="badge" :class="{ paid: inv.status === 'paid' }">{{ inv.status }}</span></td>
              <td class="muted">{{ inv.due_date || '—' }}</td>
              <td><button class="link" disabled>PDF</button></td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">No invoices yet.</p>
      </div>

      <div class="row" style="justify-content: flex-end;">
        <button class="btn" @click="$emit('close')">Done</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(20,28,40,.45); display: grid; place-items: center; z-index: 60; padding: 20px; }
.portal { width: min(560px, 96vw); max-height: 92vh; overflow: auto; display: flex; flex-direction: column; gap: 16px; }
.stripe { width: 36px; height: 36px; border-radius: 9px; background: #635bff; color: #fff; display: grid; place-items: center; font-weight: 800; }
.sim-banner { background: #fff4e0; color: #a15c00; font-size: 12px; padding: 8px 12px; border-radius: 8px; }
.block { border-top: 1px solid var(--border); padding-top: 12px; }
.block-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; color: var(--muted); margin-bottom: 8px; }
.plan { display: flex; justify-content: space-between; align-items: center; }
.badge.off { background: #eee; color: var(--muted); }
.badge.paid { background: #e6f6f2; color: #0b7a68; }
.card-chip { background: #1a1f71; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 7px; border-radius: 4px; }
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 7px 6px; border-bottom: 1px solid var(--border); font-size: 13px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.link { border: none; background: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 13px; }
.link:disabled { color: var(--muted); cursor: default; }
.icon-btn { border: none; background: none; font-size: 16px; color: var(--muted); cursor: pointer; }
</style>
