<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { SlaTiersApi } from '@/api/endpoints';

const qc = useQueryClient();
const busy = ref(false);
const error = ref('');

const { data, isLoading } = useQuery({ queryKey: ['sla-tiers'], queryFn: () => SlaTiersApi.list() });
const tiers = computed(() => data.value ?? []);

// Allowed windows: 24/48/72 hours or 1-4 days.
const UNIT_OPTS = [{ v: 'hours', l: 'Hours' }, { v: 'days', l: 'Days' }];
const VALUE_OPTS = { hours: [24, 48, 72], days: [1, 2, 3, 4] };

const deliveryLabel = (t) => (t.duration_unit === 'days'
  ? `${t.duration_value} day${t.duration_value === 1 ? '' : 's'}`
  : `${t.duration_value} hours`);
const shortLabel = (t) => `${t.duration_value}${t.duration_unit === 'days' ? 'd' : 'h'}`;

const editingId = ref(null); // null | id | 'new'
const blank = () => ({ name: '', description: '', duration_value: 24, duration_unit: 'hours' });
const form = reactive(blank());

// Keep the selected value valid whenever the unit changes.
function onUnit(u) {
  form.duration_unit = u;
  if (!VALUE_OPTS[u].includes(Number(form.duration_value))) form.duration_value = VALUE_OPTS[u][0];
}

function openNew() { Object.assign(form, blank()); editingId.value = 'new'; error.value = ''; }
function openEdit(t) { Object.assign(form, blank(), t); editingId.value = t.id; error.value = ''; }
function cancel() { editingId.value = null; error.value = ''; }

async function save() {
  if (!form.name.trim()) { error.value = 'Name is required.'; return; }
  busy.value = true; error.value = '';
  try {
    const payload = {
      name: form.name.trim(),
      description: form.description,
      duration_value: Number(form.duration_value),
      duration_unit: form.duration_unit,
    };
    if (editingId.value === 'new') await SlaTiersApi.create(payload);
    else await SlaTiersApi.update(editingId.value, payload);
    qc.invalidateQueries({ queryKey: ['sla-tiers'] });
    editingId.value = null;
  } catch (e) {
    error.value = e?.response?.data?.error?.message || 'Could not save SLA tier.';
  } finally { busy.value = false; }
}

async function remove(t) {
  if (!confirm(`Delete the "${t.name}" SLA tier? Plans using it will keep the label but lose the rule.`)) return;
  await SlaTiersApi.remove(t.id);
  qc.invalidateQueries({ queryKey: ['sla-tiers'] });
}
</script>

<template>
  <div class="stack">
    <div class="row" style="justify-content: space-between; align-items: center;">
      <div>
        <p class="muted" style="margin: 0;">
          Delivery windows used when building service plans — and, later, to set request delivery deadlines for your team.
        </p>
      </div>
      <button class="btn" @click="openNew">＋ New SLA Tier</button>
    </div>

    <!-- inline editor (new) -->
    <div v-if="editingId === 'new'" class="editor card">
      <h4>New SLA Tier</h4>
      <div class="fields">
        <label class="stack"><span>Name *</span><input v-model="form.name" class="input" placeholder="e.g. Hatch" /></label>
        <label class="stack"><span>Description</span><input v-model="form.description" class="input" placeholder="Short note on when to use this tier" /></label>
        <div class="stack"><span>Delivery within</span>
          <div class="dur">
            <select :value="form.duration_value" @change="form.duration_value = Number($event.target.value)" class="input val">
              <option v-for="v in VALUE_OPTS[form.duration_unit]" :key="v" :value="v">{{ v }}</option>
            </select>
            <div class="unit-toggle">
              <button v-for="u in UNIT_OPTS" :key="u.v" type="button" class="unit" :class="{ on: form.duration_unit === u.v }" @click="onUnit(u.v)">{{ u.l }}</button>
            </div>
          </div>
        </div>
      </div>
      <p v-if="error" class="err">{{ error }}</p>
      <div class="row" style="gap: 8px;">
        <button class="btn" :disabled="busy" @click="save">{{ busy ? 'Saving…' : 'Create tier' }}</button>
        <button class="btn secondary" @click="cancel">Cancel</button>
      </div>
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>

    <template v-for="t in tiers" :key="t.id">
      <!-- inline editor (edit) -->
      <div v-if="editingId === t.id" class="editor card">
        <h4>Edit SLA Tier</h4>
        <div class="fields">
          <label class="stack"><span>Name *</span><input v-model="form.name" class="input" /></label>
          <label class="stack"><span>Description</span><input v-model="form.description" class="input" /></label>
          <div class="stack"><span>Delivery within</span>
            <div class="dur">
              <select :value="form.duration_value" @change="form.duration_value = Number($event.target.value)" class="input val">
                <option v-for="v in VALUE_OPTS[form.duration_unit]" :key="v" :value="v">{{ v }}</option>
              </select>
              <div class="unit-toggle">
                <button v-for="u in UNIT_OPTS" :key="u.v" type="button" class="unit" :class="{ on: form.duration_unit === u.v }" @click="onUnit(u.v)">{{ u.l }}</button>
              </div>
            </div>
          </div>
        </div>
        <p v-if="error" class="err">{{ error }}</p>
        <div class="row" style="gap: 8px;">
          <button class="btn" :disabled="busy" @click="save">{{ busy ? 'Saving…' : 'Save' }}</button>
          <button class="btn secondary" @click="cancel">Cancel</button>
        </div>
      </div>

      <!-- row -->
      <div v-else class="tier card">
        <span class="clock">{{ shortLabel(t) }}</span>
        <div class="info">
          <div class="name-row"><strong>{{ t.name }}</strong><span class="slug">{{ t.slug }}</span></div>
          <p class="muted desc">{{ t.description || 'No description.' }}</p>
        </div>
        <span class="deliver">Delivery within <strong>{{ deliveryLabel(t) }}</strong></span>
        <div class="acts">
          <button class="ic edit" @click="openEdit(t)">✎</button>
          <button class="ic del" @click="remove(t)">🗑</button>
        </div>
      </div>
    </template>

    <p v-if="!isLoading && !tiers.length && editingId !== 'new'" class="muted">
      No SLA tiers yet. Click New SLA Tier to create one.
    </p>
  </div>
</template>

<style scoped>
.editor { padding: 16px; }
.editor h4 { margin: 0 0 12px; }
.fields { display: grid; gap: 14px; margin-bottom: 14px; }
.stack { display: flex; flex-direction: column; gap: 6px; }
.stack > span { font-size: 13px; color: var(--muted); }
.dur { display: flex; gap: 10px; align-items: center; }
.val { max-width: 110px; }
.unit-toggle { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.unit { border: none; background: var(--surface); padding: 8px 16px; cursor: pointer; font-size: 14px; }
.unit.on { background: var(--primary); color: #fff; font-weight: 600; }
.err { color: var(--danger); font-size: 13px; margin: 0 0 10px; }

.tier { display: flex; align-items: center; gap: 16px; padding: 14px 16px; }
.clock { flex: none; width: 46px; height: 46px; border-radius: 12px; background: #eef2fb; color: var(--primary); display: grid; place-items: center; font-weight: 700; font-size: 15px; }
.info { flex: 1; min-width: 0; }
.name-row { display: flex; align-items: center; gap: 8px; }
.slug { font-size: 11px; color: var(--muted); background: var(--bg); padding: 1px 7px; border-radius: 999px; }
.desc { margin: 3px 0 0; font-size: 13px; }
.deliver { font-size: 13px; color: var(--muted); white-space: nowrap; }
.deliver strong { color: var(--text); }
.acts { display: flex; gap: 8px; }
.ic { border: 1px solid var(--border); background: var(--surface); border-radius: 8px; width: 30px; height: 30px; cursor: pointer; }
.ic.edit { color: var(--primary); } .ic.del { color: var(--danger); }
</style>
