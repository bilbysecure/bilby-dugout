<script setup>
import { ref } from 'vue';
import { UploadApi } from '@/api/endpoints';
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue';

const props = defineProps({
  form: { type: Object, required: true },
  serviceOptions: { type: Array, default: () => [] },
  slaTiers: { type: Array, default: () => [] }, // [{ value: slug, label }] from Admin → SLA Tiers
  busy: Boolean,
  error: String,
  mode: { type: String, default: 'new' },
});
defineEmits(['save', 'cancel']);

const uploading = ref(false);
async function uploadImage(e) {
  const f = e.target.files?.[0];
  if (!f) return;
  uploading.value = true;
  try { props.form.image_url = await UploadApi.image(f); } finally { uploading.value = false; }
}

const PRICING = [
  { value: 'standard', label: 'Standard', desc: 'Set your price, and receive requests from clients.' },
  { value: 'time_based', label: 'Time Based', desc: 'Includes an amount of hours that clients can use for requests.' },
  { value: 'credit_based', label: 'Credit Based', desc: 'Includes credits that clients can redeem for requests.' },
];
const PERIODS = ['weekly', 'monthly', 'quarterly', 'biannually', 'annually'];
const LIMIT_OPTS = [{ v: 1, l: '1' }, { v: 2, l: '2' }, { v: 3, l: '3' }, { v: 0, l: 'Unlimited' }];
const TRIAL_UNITS = ['days', 'weeks', 'months'];

function toggleService(label) {
  const arr = props.form.services;
  const i = arr.indexOf(label);
  if (i === -1) arr.push(label); else arr.splice(i, 1);
}
</script>

<template>
  <div class="editor">
    <!-- Pricing model -->
    <div class="field">
      <span class="flabel">Pricing model</span>
      <div class="pricing">
        <button
          v-for="pt in PRICING" :key="pt.value" type="button"
          class="ptype" :class="{ on: form.pricing_type === pt.value }" @click="form.pricing_type = pt.value"
        >
          <strong>{{ pt.label }}</strong>
          <span class="muted">{{ pt.desc }}</span>
        </button>
      </div>
    </div>

    <div class="grid3">
      <label class="stack"><span>Price</span>
        <div class="price-input">
          <span class="cur pre">A$</span>
          <input v-model="form.price" type="number" min="0" step="0.01" placeholder="0.00" />
          <span class="cur suf">AUD</span>
        </div>
      </label>
      <label class="stack"><span>Recurring period</span>
        <select v-model="form.billing_period" class="input"><option v-for="p in PERIODS" :key="p" :value="p">{{ p }}</option></select>
      </label>
      <label v-if="form.pricing_type === 'time_based'" class="stack"><span>Hours included</span><input v-model="form.hours" class="input" type="number" min="0" /></label>
      <label v-else-if="form.pricing_type === 'credit_based'" class="stack"><span>Credits included</span><input v-model="form.credits" class="input" type="number" min="0" /></label>
      <div v-else />
    </div>

    <!-- Setup fee -->
    <div class="setup">
      <div class="toggle">
        <ToggleSwitch v-model="form.setup_fee_enabled" />
        <span>Charge a setup fee</span>
      </div>
      <label v-if="form.setup_fee_enabled" class="stack" style="max-width: 320px; margin-top: 10px;"><span>Setup fee amount</span>
        <div class="price-input">
          <span class="cur pre">A$</span>
          <input v-model="form.setup_fee_amount" type="number" min="0" step="0.01" placeholder="0.00" />
          <span class="cur suf">AUD</span>
        </div>
      </label>
    </div>

    <!-- Core fields -->
    <div class="grid2">
      <label class="stack"><span>Plan Name *</span><input v-model="form.name" class="input" /></label>
      <label class="stack"><span>SLA Tier</span>
        <select v-model="form.sla_tier" class="input">
          <option v-if="!slaTiers.length" value="">No SLA tiers — add them in Admin → SLA Tiers</option>
          <option v-for="s in slaTiers" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </label>
      <label class="stack"><span>Monthly Request Limit (0 = unlimited)</span><input v-model.number="form.monthly_request_limit" class="input" type="number" min="0" /></label>
      <label class="stack"><span>Concurrent Requests</span>
        <select v-model.number="form.concurrent_request_limit" class="input"><option v-for="o in LIMIT_OPTS" :key="o.v" :value="o.v">{{ o.l }}</option></select>
      </label>
      <label class="stack"><span>Brand Kits Allowed</span>
        <select v-model.number="form.brand_kit_allowance" class="input"><option v-for="o in LIMIT_OPTS" :key="o.v" :value="o.v">{{ o.v === 1 ? '1 brand' : (o.v === 0 ? 'Unlimited' : o.v + ' brands') }}</option></select>
      </label>
      <label class="stack"><span>Badge Colour</span>
        <div class="row" style="gap: 10px; align-items: center;">
          <input type="color" v-model="form.badge_color" class="color" />
          <span class="muted">{{ form.badge_color }}</span>
        </div>
      </label>
    </div>

    <label class="stack"><span>Description</span><input v-model="form.description" class="input" /></label>

    <div class="field">
      <span class="flabel">Plan image <span class="muted" style="text-transform: none; font-weight: 400;">— shown on the catalog / purchase page</span></span>
      <div class="row" style="gap: 12px; align-items: center;">
        <div class="cover-box">
          <img v-if="form.image_url" :src="form.image_url" alt="" />
          <span v-else>🖼️</span>
        </div>
        <label class="btn secondary small">{{ uploading ? 'Uploading…' : (form.image_url ? 'Change image' : 'Upload image') }}<input type="file" accept="image/*" hidden @change="uploadImage" /></label>
        <button v-if="form.image_url" type="button" class="link" @click="form.image_url = ''">Remove</button>
      </div>
    </div>

    <div class="toggle">
      <ToggleSwitch v-model="form.is_default" />
      <span>Make this the default service plan</span>
    </div>

    <!-- Trial period -->
    <div class="trial">
      <div class="toggle">
        <ToggleSwitch v-model="form.trial_enabled" />
        <span>Enable trial period</span>
      </div>
      <div v-if="form.trial_enabled" class="trial-fields">
        <label class="stack"><span>Trial Amount</span>
          <div class="price-input">
            <span class="cur pre">A$</span>
            <input v-model="form.trial_amount" type="number" min="0" step="0.01" placeholder="0.00" />
            <span class="cur suf">AUD</span>
          </div>
        </label>
        <label class="stack"><span>Trial period</span>
          <div class="row" style="gap: 8px;">
            <input v-model.number="form.trial_period_count" type="number" min="1" class="input" style="max-width: 100px;" />
            <select v-model="form.trial_period_unit" class="input"><option v-for="u in TRIAL_UNITS" :key="u" :value="u">{{ u }}</option></select>
          </div>
        </label>
        <p class="muted hint">
          <template v-if="Number(form.trial_amount || 0) === 0">🎁 A trial amount of 0 means a <strong>free trial</strong>.</template>
          <template v-else>Clients are charged A${{ Number(form.trial_amount).toFixed(2) }} AUD for the trial.</template>
        </p>
      </div>
    </div>

    <div class="field">
      <span class="flabel">Included Services *</span>
      <div class="services">
        <button
          v-for="s in serviceOptions" :key="s" type="button"
          class="svc" :class="{ on: form.services.includes(s) }" @click="toggleService(s)"
        ><template v-if="form.services.includes(s)">✓ </template>{{ s }}</button>
      </div>
    </div>

    <p v-if="error" class="err">{{ error }}</p>
    <div class="row" style="gap: 14px; align-items: center;">
      <button class="btn" :disabled="busy" @click="$emit('save')">{{ mode === 'new' ? 'Save Plan' : '✓ Save' }}</button>
      <button class="link" @click="$emit('cancel')">✕ Cancel</button>
    </div>
  </div>
</template>

<style scoped>
.editor { border: 1px solid #c7d2fe; background: #f7f8ff; border-radius: var(--radius); padding: 20px; display: flex; flex-direction: column; gap: 16px; }
.field .flabel { font-size: 13px; color: var(--muted); display: block; margin-bottom: 8px; }
.pricing { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.ptype { text-align: left; border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); padding: 12px; display: flex; flex-direction: column; gap: 4px; cursor: pointer; }
.ptype.on { border-color: var(--primary); box-shadow: 0 0 0 2px #eef2f8; }
.ptype strong { font-size: 14px; }
.ptype .muted { font-size: 12px; }
.grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
label.stack span { font-size: 13px; color: var(--muted); }
.color { width: 44px; height: 34px; border: 1px solid var(--border); border-radius: 8px; padding: 2px; background: var(--surface); }
.toggle { display: flex; align-items: center; gap: 8px; font-size: 14px; }
.setup { border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; background: var(--surface); }
.trial { border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; background: var(--surface); display: flex; flex-direction: column; gap: 12px; }
.trial-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; align-items: end; }
.trial-fields .hint { grid-column: 1 / -1; margin: 0; font-size: 12px; }
@media (max-width: 760px) { .trial-fields { grid-template-columns: 1fr; } }
.services { display: flex; flex-wrap: wrap; gap: 8px; }
.svc { border: 1px solid var(--border); background: var(--surface); padding: 8px 14px; border-radius: 999px; font-size: 14px; cursor: pointer; }
.svc.on { background: var(--primary); border-color: var(--primary); color: #fff; }
.price-input { display: flex; align-items: center; border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); overflow: hidden; }
.price-input input { border: none; flex: 1; padding: 9px 8px; outline: none; background: transparent; font: inherit; }
.cur { color: var(--muted); font-size: 13px; padding: 0 10px; background: var(--bg); align-self: stretch; display: flex; align-items: center; }
.cur.pre { border-right: 1px solid var(--border); }
.cur.suf { border-left: 1px solid var(--border); font-weight: 600; }
.cover-box { width: 96px; height: 64px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); display: grid; place-items: center; overflow: hidden; font-size: 22px; }
.cover-box img { width: 100%; height: 100%; object-fit: cover; }
.small { font-size: 13px; padding: 6px 12px; }
.err { color: var(--danger); margin: 0; }
.link { border: none; background: none; color: var(--primary); cursor: pointer; }
@media (max-width: 760px) { .pricing, .grid3, .grid2 { grid-template-columns: 1fr; } }
</style>
