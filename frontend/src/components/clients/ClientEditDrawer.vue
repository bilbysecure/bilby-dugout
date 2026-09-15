<script setup>
import { reactive, ref, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { SubscriptionsApi, PlansApi, AdminApi, UploadApi } from '@/api/endpoints';

const props = defineProps({ client: { type: Object, required: true } });
const emit = defineEmits(['close']);

const qc = useQueryClient();
const { data: planData } = useQuery({ queryKey: ['service-plans'], queryFn: () => PlansApi.list() });
const { data: staffData } = useQuery({ queryKey: ['team-members'], queryFn: () => AdminApi.teamMembers() });
const plans = computed(() => planData.value ?? []);
const staff = computed(() => (staffData.value ?? []).filter((s) => s.status === 'active'));

const SLA = [{ v: 'hatch', l: 'Hatch' }, { v: 'sprint', l: 'Sprint' }, { v: 'burrow', l: 'Burrow' }];
const STATUS = ['active', 'paused', 'cancelled'];

const form = reactive({
  client_name: props.client.client_name || '', company_name: props.client.company_name || '',
  avatar_url: props.client.avatar_url || '', plan_id: props.client.plan_id || '', plan_name: props.client.plan_name || '',
  sla_tier: props.client.sla_tier || 'sprint', status: props.client.status || 'active',
  monthly_request_limit: props.client.monthly_request_limit ?? 0, renewal_date: props.client.renewal_date || '',
  account_managers: [...(props.client.account_managers || [])], notes: props.client.notes || '',
});
const uploading = ref(false);
const error = ref('');
const newManager = ref('');

function onPlan(e) {
  const p = plans.value.find((x) => String(x.id) === String(e.target.value));
  if (p) { form.plan_name = p.name; if (p.sla_tier) form.sla_tier = p.sla_tier; if (p.monthly_request_limit != null) form.monthly_request_limit = p.monthly_request_limit; }
}
async function uploadPhoto(e) { const f = e.target.files?.[0]; if (!f) return; uploading.value = true; try { form.avatar_url = await UploadApi.image(f); } finally { uploading.value = false; } }
function addManager() {
  const s = staff.value.find((x) => x.email === newManager.value);
  if (s && !form.account_managers.some((m) => m.email === s.email)) form.account_managers.push({ email: s.email, name: s.full_name });
  newManager.value = '';
}
function removeManager(email) { form.account_managers = form.account_managers.filter((m) => m.email !== email); }

const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => SubscriptionsApi.update(props.client.id, { ...form }),
  onSuccess: () => { qc.invalidateQueries({ queryKey: ['subscriptions'] }); emit('close'); },
  onError: (e) => { error.value = e?.response?.data?.error?.message || 'Could not save.'; },
});
const initial = (n) => (n || '?').trim().charAt(0).toUpperCase();
</script>

<template>
  <div class="drawer-overlay" @click.self="emit('close')">
    <aside class="drawer">
      <header class="d-head">
        <div class="row" style="gap: 10px; align-items: center;">
          <span class="logo">{{ initial(client.company_name || client.client_name) }}</span>
          <strong>Edit Client</strong>
        </div>
        <button class="x" @click="emit('close')">✕</button>
      </header>

      <div class="section-title">👤 CLIENT INFO</div>
      <div class="card2">
        <div class="row" style="gap: 12px; align-items: center;">
          <span class="avatar lg"><img v-if="form.avatar_url" :src="form.avatar_url" alt="" /><span v-else>{{ initial(form.company_name) }}</span></span>
          <label class="btn secondary small">📷 {{ uploading ? 'Uploading…' : 'Change Photo' }}<input type="file" accept="image/*" hidden @change="uploadPhoto" /></label>
          <button v-if="form.avatar_url" class="link danger" @click="form.avatar_url = ''">Remove</button>
        </div>
        <label class="stack"><span>Contact Name</span><input v-model="form.client_name" class="input" /></label>
        <label class="stack"><span>Company Name</span><input v-model="form.company_name" class="input" /></label>
        <label class="stack"><span>Email</span><input :value="client.client_email" class="input" disabled /></label>
      </div>

      <div class="section-title">💳 SUBSCRIPTION</div>
      <div class="card2 grid2">
        <label class="stack"><span>Plan Name</span>
          <select :value="form.plan_id" class="input" @change="onPlan">
            <option value="">—</option>
            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>
        <label class="stack"><span>Status</span>
          <select v-model="form.status" class="input"><option v-for="s in STATUS" :key="s" :value="s">{{ s }}</option></select>
        </label>
        <label class="stack"><span>SLA Tier</span>
          <select v-model="form.sla_tier" class="input"><option v-for="s in SLA" :key="s.v" :value="s.v">{{ s.l }}</option></select>
        </label>
        <label class="stack"><span>Monthly Request Limit</span><input v-model.number="form.monthly_request_limit" class="input" type="number" min="0" /></label>
      </div>

      <div class="section-title">🗓 ACCOUNT DETAILS</div>
      <div class="card2">
        <label class="stack"><span>Renewal Date</span><input v-model="form.renewal_date" class="input" type="date" /></label>
        <label class="stack"><span>Account Managers</span>
          <select v-model="newManager" class="input" @change="addManager">
            <option value="">+ Add account manager…</option>
            <option v-for="s in staff" :key="s.email" :value="s.email">{{ s.full_name }}</option>
          </select>
        </label>
        <div class="chips">
          <span v-for="m in form.account_managers" :key="m.email" class="chip">{{ m.name }} <button class="x2" @click="removeManager(m.email)">✕</button></span>
        </div>
        <label class="stack"><span>Notes</span><textarea v-model="form.notes" class="input" rows="3" placeholder="Internal notes about this client" /></label>
      </div>

      <p v-if="error" class="err">{{ error }}</p>
      <div class="row" style="justify-content: flex-end; gap: 10px;">
        <button class="link" @click="emit('close')">Cancel</button>
        <button class="btn" :disabled="saving" @click="save()">✓ Save Changes</button>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.drawer-overlay { position: fixed; inset: 0; background: rgba(20,28,40,.4); z-index: 60; display: flex; justify-content: flex-end; }
.drawer { width: min(460px, 96vw); background: var(--bg); height: 100%; overflow: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
.d-head { display: flex; justify-content: space-between; align-items: center; }
.logo { width: 34px; height: 34px; border-radius: 50%; background: #ede9fe; color: #6d28d9; display: grid; place-items: center; font-weight: 700; }
.x { border: none; background: none; font-size: 16px; color: var(--muted); cursor: pointer; }
.section-title { font-size: 11px; font-weight: 700; letter-spacing: .05em; color: var(--muted); margin-top: 4px; }
.card2 { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; display: flex; flex-direction: column; gap: 12px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.avatar.lg { width: 56px; height: 56px; border-radius: 50%; background: #ede9fe; color: #6d28d9; display: grid; place-items: center; font-size: 18px; font-weight: 700; overflow: hidden; }
.avatar.lg img { width: 100%; height: 100%; object-fit: cover; }
label.stack span { font-size: 12px; color: var(--muted); }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { background: #eef2fb; color: var(--primary); border-radius: 999px; padding: 3px 10px; font-size: 13px; }
.x2 { border: none; background: none; color: inherit; cursor: pointer; }
.link { border: none; background: none; color: var(--primary); cursor: pointer; }
.link.danger { color: var(--danger); }
.small { font-size: 13px; padding: 6px 10px; }
.err { color: var(--danger); margin: 0; }
</style>
