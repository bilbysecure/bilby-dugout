<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { PlansApi, FormConfigApi, SlaTiersApi } from '@/api/endpoints';
import PlanEditor from '@/components/settings/PlanEditor.vue';

const qc = useQueryClient();
const busy = ref(false);
const error = ref('');

const { data: plansData, isLoading } = useQuery({ queryKey: ['service-plans'], queryFn: () => PlansApi.list() });
const { data: svcData } = useQuery({ queryKey: ['form-config', 'service_types'], queryFn: () => FormConfigApi.get('service_types') });
const { data: slaData } = useQuery({ queryKey: ['sla-tiers'], queryFn: () => SlaTiersApi.list() });
const plans = computed(() => plansData.value ?? []);
const serviceOptions = computed(() => (svcData.value ?? []).map((s) => s.label));

// SLA tiers come from the managed Admin → SLA Tiers list.
const slaDur = (t) => (t.duration_unit === 'days' ? `${t.duration_value}d` : `${t.duration_value}h`);
const slaTiers = computed(() => (slaData.value ?? []).map((t) => ({ value: t.slug, label: `${t.name} (${slaDur(t)})` })));
const slaLabel = (v) => slaTiers.value.find((s) => s.value === v)?.label || v;
const reqLabel = (p) => (p.monthly_request_limit > 0 ? `${p.monthly_request_limit} req/mo` : 'Unlimited');
const concLabel = (p) => (p.concurrent_request_limit > 0 ? `${p.concurrent_request_limit} concurrent` : 'Unlimited concurrent');
const brandLabel = (p) => (p.brand_kit_allowance > 0 ? `${p.brand_kit_allowance} brand${p.brand_kit_allowance > 1 ? 's' : ''}` : 'Unlimited brands');
const formatPrice = (amount) => {
  if (amount == null || amount === '') return null;
  const n = Number(amount);
  if (Number.isNaN(n)) return null;
  return `${new Intl.NumberFormat('en-US', { style: 'currency', currency: 'AUD' }).format(n)} AUD`;
};
const trialLabel = (p) => {
  if (!p.trial_enabled) return null;
  const count = p.trial_period_count || 0;
  const unit = count === 1 ? (p.trial_period_unit || 'days').replace(/s$/, '') : (p.trial_period_unit || 'days');
  const period = `${count}-${unit}`;
  const amt = Number(p.trial_amount);
  return (!p.trial_amount || amt === 0) ? `${period} free trial` : `${period} trial · ${formatPrice(p.trial_amount)}`;
};

const editingId = ref(null); // null | id | 'new'
const blank = () => ({
  name: '', description: '', sla_tier: 'sprint', monthly_request_limit: 0, concurrent_request_limit: 2,
  brand_kit_allowance: 1, badge_color: '#6366f1', is_default: false, image_url: '',
  pricing_type: 'standard', billing_period: 'monthly', price: '', hours: null, credits: null, services: [],
  trial_enabled: false, trial_amount: '', trial_period_count: 14, trial_period_unit: 'days',
  setup_fee_enabled: false, setup_fee_amount: '',
});
const form = reactive(blank());

function openNew() { Object.assign(form, blank()); editingId.value = 'new'; error.value = ''; }
function openEdit(p) { Object.assign(form, blank(), p, { services: [...(p.services || [])] }); editingId.value = p.id; error.value = ''; }
function cancel() { editingId.value = null; }

async function save() {
  if (!form.name.trim()) { error.value = 'Plan name is required.'; return; }
  if (!form.services.length) { error.value = 'Select at least one service.'; return; }
  busy.value = true; error.value = '';
  try {
    const payload = {
      ...form,
      price: form.price === '' || form.price == null ? null : Number(form.price),
      trial_amount: !form.trial_enabled || form.trial_amount === '' || form.trial_amount == null ? null : Number(form.trial_amount),
      setup_fee_amount: !form.setup_fee_enabled || form.setup_fee_amount === '' || form.setup_fee_amount == null ? null : Number(form.setup_fee_amount),
    };
    if (editingId.value === 'new') await PlansApi.create(payload);
    else await PlansApi.update(editingId.value, payload);
    qc.invalidateQueries({ queryKey: ['service-plans'] });
    editingId.value = null;
  } catch (e) {
    error.value = e?.response?.data?.error?.message || 'Could not save plan.';
  } finally { busy.value = false; }
}
async function remove(id) { if (!confirm('Delete this plan?')) return; await PlansApi.remove(id); qc.invalidateQueries({ queryKey: ['service-plans'] }); }
</script>

<template>
  <div class="stack">
    <div class="row" style="justify-content: space-between; align-items: center;">
      <span class="muted">{{ plans.length }} plan{{ plans.length === 1 ? '' : 's' }} defined</span>
      <button class="btn" @click="openNew">＋ New Plan</button>
    </div>

    <PlanEditor v-if="editingId === 'new'" :form="form" :service-options="serviceOptions" :sla-tiers="slaTiers" :busy="busy" :error="error" mode="new" @save="save" @cancel="cancel" />

    <p v-if="isLoading" class="muted">Loading…</p>
    <template v-for="p in plans" :key="p.id">
      <PlanEditor v-if="editingId === p.id" :form="form" :service-options="serviceOptions" :sla-tiers="slaTiers" :busy="busy" :error="error" mode="edit" @save="save" @cancel="cancel" />
      <div v-else class="plan card">
        <div class="plan-actions">
          <button class="ic edit" @click="openEdit(p)">✎</button>
          <button class="ic del" @click="remove(p.id)">🗑</button>
        </div>
        <div class="plan-head">
          <img v-if="p.image_url" :src="p.image_url" class="cover" alt="" />
          <span class="dot" :style="{ background: p.badge_color || '#94a3b8' }" />
          <strong>{{ p.name }}</strong>
          <span class="sla">{{ slaLabel(p.sla_tier) }}</span>
          <span class="muted meta">{{ reqLabel(p) }} · {{ concLabel(p) }} · {{ brandLabel(p) }}</span>
          <span v-if="p.is_default" class="badge def">Default</span>
          <span v-if="trialLabel(p)" class="badge trial">🎁 {{ trialLabel(p) }}</span>
        </div>
        <div v-if="formatPrice(p.price)" class="price">
          {{ formatPrice(p.price) }}
          <span class="per">/ {{ p.billing_period }}</span>
          <span v-if="p.pricing_type === 'time_based' && p.hours" class="unit">· {{ p.hours }} hrs</span>
          <span v-else-if="p.pricing_type === 'credit_based' && p.credits" class="unit">· {{ p.credits }} credits</span>
          <span v-if="p.setup_fee_enabled && Number(p.setup_fee_amount) > 0" class="unit">+ {{ formatPrice(p.setup_fee_amount) }} setup</span>
        </div>
        <p class="muted desc">{{ p.description }}</p>
        <div class="chips">
          <span v-for="(s, i) in p.services" :key="i" class="tag">{{ s }}</span>
        </div>
      </div>
    </template>
    <p v-if="!isLoading && !plans.length && editingId !== 'new'" class="muted">No plans yet. Click New Plan to create one.</p>
  </div>
</template>

<style scoped>
.plan { position: relative; padding: 16px 16px 14px; }
.plan-actions { position: absolute; top: 14px; right: 14px; display: flex; gap: 8px; }
.ic { border: 1px solid var(--border); background: var(--surface); border-radius: 8px; width: 30px; height: 30px; cursor: pointer; }
.ic.edit { color: var(--primary); } .ic.del { color: var(--danger); }
.plan-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.dot { width: 11px; height: 11px; border-radius: 50%; }
.cover { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
.sla { background: #fdece9; color: #c0392b; font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 999px; }
.meta { font-size: 13px; }
.badge.def { background: #e6f6f2; color: #0b7a68; }
.badge.trial { background: #eef2fb; color: var(--primary); }
.price { margin-top: 8px; font-size: 16px; font-weight: 700; color: var(--text); }
.price .per, .price .unit { font-size: 12px; font-weight: 500; color: var(--muted); }
.desc { margin: 6px 0 10px; font-size: 13px; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.tag { background: #eef0f3; color: #6b7480; font-size: 12px; padding: 3px 10px; border-radius: 999px; }
</style>
