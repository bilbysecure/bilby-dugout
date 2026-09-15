<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { BrandApi, AdminApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';
import AssetGallery from '@/components/brand/AssetGallery.vue';

const auth = useAuthStore();
const qc = useQueryClient();

const { data: kitsData, isLoading } = useQuery({ queryKey: ['brand-kits'], queryFn: () => BrandApi.listKits() });
const { data: subsData } = useQuery({ queryKey: ['subscriptions'], queryFn: () => AdminApi.subscriptions() });
const kits = computed(() => kitsData.value ?? []);
const subs = computed(() => subsData.value ?? []);
const clientLabel = (email) => {
  const s = subs.value.find((x) => x.client_email === email);
  return s ? `${s.client_name || s.company_name || email} (${s.company_name || email})` : email;
};

const search = ref('');
const filteredKits = computed(() => {
  const q = search.value.toLowerCase();
  return kits.value.filter((k) => !q || (k.brand_name || '').toLowerCase().includes(q));
});

const canWrite = computed(() => auth.isAgency || auth.role === 'client_owner');

// ── selection + editable form ────────────────────────────────
const selectedId = ref(null);
const isNew = ref(false);
const error = ref('');
const blank = () => ({
  brand_name: '', tagline: '', client_email: auth.user?.client_email || (subs.value[0]?.client_email ?? ''),
  website: '', industry: '', notes: '', colors: [], typography: [],
  brand_voice_tone: '', brand_voice_personality: '', brand_voice_messaging: '', brand_voice_writing_guidelines: '',
  guidelines_pdf_url: '',
});
const form = reactive(blank());
const tab = ref('identity');

function loadInto(kit) {
  Object.assign(form, blank(), kit, {
    colors: Array.isArray(kit.colors) ? kit.colors.map((c) => ({ ...c })) : [],
    typography: Array.isArray(kit.typography) ? kit.typography.map((t) => ({ ...t })) : [],
  });
}
function select(kit) { isNew.value = false; selectedId.value = kit.id; error.value = ''; loadInto(kit); tab.value = 'identity'; }
function newBrand() { isNew.value = true; selectedId.value = null; error.value = ''; Object.assign(form, blank()); tab.value = 'identity'; }

// auto-select first kit once loaded
watch(kits, (list) => { if (!isNew.value && selectedId.value == null && list.length) select(list[0]); }, { immediate: true });

// brand allowance for the current client context
const { data: allowData } = useQuery({
  queryKey: computed(() => ['brand-allowance', form.client_email]),
  queryFn: () => BrandApi.allowance(form.client_email),
  enabled: computed(() => !!form.client_email),
});
const allowance = computed(() => allowData.value ?? null);
const limitReached = computed(() => allowance.value && allowance.value.allowed > 0 && allowance.value.used >= allowance.value.allowed);

// ── mutations ────────────────────────────────────────────────
function payload() {
  return { ...form, colors: form.colors.filter((c) => c.hex), typography: form.typography.filter((t) => t.font_name) };
}
const invalidate = () => { qc.invalidateQueries({ queryKey: ['brand-kits'] }); qc.invalidateQueries({ queryKey: ['brand-allowance'] }); };
const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => (isNew.value ? BrandApi.createKit(payload()) : BrandApi.updateKit(selectedId.value, payload())),
  onSuccess: (kit) => { invalidate(); isNew.value = false; selectedId.value = kit.id; loadInto(kit); },
  onError: (e) => { error.value = e?.response?.data?.error?.message || 'Could not save.'; },
});
const { mutate: destroy } = useMutation({
  mutationFn: () => BrandApi.deleteKit(selectedId.value),
  onSuccess: () => { invalidate(); selectedId.value = null; if (kits.value.length) select(kits.value.find((k) => k.id !== selectedId.value) || kits.value[0]); },
});

const tabs = [
  { key: 'identity', label: 'Identity' },
  { key: 'colours', label: 'Colours', count: () => form.colors.length },
  { key: 'typography', label: 'Typography', count: () => form.typography.length },
  { key: 'voice', label: 'Brand Voice' },
  { key: 'logos', label: 'Logos', cat: 'logo' },
  { key: 'photos', label: 'Photos', cat: 'photo' },
  { key: 'icons', label: 'Icons', cat: 'icon' },
  { key: 'graphics', label: 'Graphics', cat: 'graphic' },
  { key: 'guidelines', label: 'Guidelines (PDF)' },
];
</script>

<template>
  <div class="hub">
    <!-- Sidebar -->
    <aside class="side">
      <input v-model="search" class="input" placeholder="🔍 Search brands…" />
      <button v-if="canWrite" class="btn new" @click="newBrand">＋ New Brand</button>
      <div v-if="allowance" class="muted allow">{{ allowance.used }} of {{ allowance.allowed || '∞' }} brands used</div>

      <div class="brand-list">
        <button
          v-for="k in filteredKits" :key="k.id"
          class="brand-item" :class="{ active: selectedId === k.id }" @click="select(k)"
        >
          <div class="bi-main">
            <strong>{{ k.brand_name || 'Untitled' }}</strong>
            <span class="muted">{{ (k.client_email || '').split('@')[0] }}</span>
          </div>
          <span class="swatches">
            <span v-for="(c, i) in (k.colors || []).slice(0, 4)" :key="i" class="sw" :style="{ background: c.hex }" />
          </span>
        </button>
        <p v-if="isLoading" class="muted">Loading…</p>
        <p v-else-if="!filteredKits.length" class="muted">No brands.</p>
      </div>
    </aside>

    <!-- Main -->
    <main class="main" v-if="selectedId != null || isNew">
      <header class="row" style="justify-content: space-between; align-items: flex-start;">
        <div>
          <h2>{{ form.brand_name || (isNew ? 'New Brand' : 'Untitled') }}</h2>
          <p class="muted">{{ form.client_email }}</p>
        </div>
        <div class="row" style="gap: 8px;">
          <button v-if="canWrite && !isNew" class="btn ghost-danger" @click="destroy()">🗑 Delete</button>
          <button v-if="canWrite" class="btn" :disabled="!form.brand_name || saving" @click="save()">💾 Save Changes</button>
        </div>
      </header>

      <div class="tabs">
        <button v-for="t in tabs" :key="t.key" class="tab" :class="{ active: tab === t.key }" @click="tab = t.key">
          {{ t.label }}<span v-if="t.count && t.count()" class="cnt">{{ t.count() }}</span>
        </button>
      </div>

      <p v-if="error" class="err">{{ error }}</p>

      <div class="panel card">
        <!-- Identity -->
        <div v-if="tab === 'identity'" class="grid2">
          <label class="stack"><span>Brand Name *</span><input v-model="form.brand_name" class="input" /></label>
          <label class="stack"><span>Tagline / Slogan</span><input v-model="form.tagline" class="input" /></label>
          <label class="stack span2"><span>Client</span>
            <select v-model="form.client_email" class="input" :disabled="!auth.isAgency || !isNew">
              <option v-for="s in subs" :key="s.client_email" :value="s.client_email">{{ clientLabel(s.client_email) }}</option>
            </select>
          </label>
          <label class="stack"><span>Website</span><input v-model="form.website" class="input" placeholder="https://…" /></label>
          <label class="stack"><span>Industry</span><input v-model="form.industry" class="input" /></label>
          <label class="stack span2"><span>Additional Notes</span><textarea v-model="form.notes" class="input" rows="3" /></label>
        </div>

        <!-- Colours -->
        <div v-else-if="tab === 'colours'" class="stack">
          <div v-for="(c, i) in form.colors" :key="i" class="row rowline">
            <input type="color" v-model="c.hex" class="color" />
            <input v-model="c.role" class="input" placeholder="Role (e.g. Primary)" style="max-width: 220px;" />
            <input v-model="c.hex" class="input" placeholder="#000000" style="max-width: 130px;" />
            <button class="link danger" @click="form.colors.splice(i, 1)">Remove</button>
          </div>
          <button class="btn secondary small" @click="form.colors.push({ role: '', hex: '#2e5496' })">＋ Add colour</button>
        </div>

        <!-- Typography -->
        <div v-else-if="tab === 'typography'" class="stack">
          <div v-for="(t, i) in form.typography" :key="i" class="row rowline">
            <input v-model="t.role" class="input" placeholder="Role (e.g. Heading)" style="max-width: 220px;" />
            <input v-model="t.font_name" class="input" placeholder="Font name (e.g. Inter)" :style="{ maxWidth: '260px', fontFamily: t.font_name || 'inherit' }" />
            <button class="link danger" @click="form.typography.splice(i, 1)">Remove</button>
          </div>
          <button class="btn secondary small" @click="form.typography.push({ role: '', font_name: '' })">＋ Add font</button>
        </div>

        <!-- Brand Voice -->
        <div v-else-if="tab === 'voice'" class="grid2">
          <label class="stack"><span>Tone</span><input v-model="form.brand_voice_tone" class="input" /></label>
          <label class="stack"><span>Personality</span><input v-model="form.brand_voice_personality" class="input" /></label>
          <label class="stack span2"><span>Messaging</span><textarea v-model="form.brand_voice_messaging" class="input" rows="3" /></label>
          <label class="stack span2"><span>Writing guidelines</span><textarea v-model="form.brand_voice_writing_guidelines" class="input" rows="3" /></label>
        </div>

        <!-- Asset galleries -->
        <AssetGallery
          v-else-if="['logos', 'photos', 'icons', 'graphics'].includes(tab) && !isNew"
          :kit-id="selectedId" :category="tabs.find((t) => t.key === tab).cat" :label="tabs.find((t) => t.key === tab).label.replace(/s$/, '')"
          :can-write="canWrite"
        />
        <p v-else-if="['logos', 'photos', 'icons', 'graphics'].includes(tab)" class="muted">Save the brand first to add assets.</p>

        <!-- Guidelines -->
        <div v-else class="stack">
          <label class="stack"><span>Brand guidelines PDF URL</span>
            <input v-model="form.guidelines_pdf_url" class="input" placeholder="https://…/guidelines.pdf" />
          </label>
          <a v-if="form.guidelines_pdf_url" :href="form.guidelines_pdf_url" target="_blank" class="btn secondary small" style="width: fit-content;">📄 View guidelines</a>
        </div>
      </div>
    </main>
    <main v-else class="main"><p class="muted">Select a brand or create a new one.</p></main>
  </div>
</template>

<style scoped>
.hub { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
.side { position: sticky; top: 0; display: flex; flex-direction: column; gap: 10px; }
.new { width: 100%; }
.allow { font-size: 12px; }
.brand-list { display: flex; flex-direction: column; gap: 6px; margin-top: 4px; }
.brand-item { text-align: left; border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.brand-item.active { border-color: var(--primary); background: #eef2f8; }
.bi-main { display: flex; flex-direction: column; }
.bi-main .muted { font-size: 12px; }
.swatches { display: inline-flex; gap: 3px; }
.sw { width: 12px; height: 12px; border-radius: 50%; border: 1px solid rgba(0,0,0,.1); }
.tabs { display: flex; gap: 4px; flex-wrap: wrap; }
.tab { border: 1px solid transparent; background: none; padding: 7px 12px; border-radius: 999px; font-size: 14px; color: var(--muted); }
.tab.active { background: var(--surface); border-color: var(--border); color: var(--text); font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
.cnt { background: #eef2f8; color: var(--primary); font-size: 11px; padding: 1px 6px; border-radius: 999px; margin-left: 6px; }
.panel { padding: 20px; margin-top: 4px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.span2 { grid-column: 1 / -1; }
label.stack span { font-size: 13px; color: var(--muted); margin-bottom: 2px; display: block; }
.rowline { align-items: center; gap: 10px; }
.color { width: 40px; height: 34px; border: 1px solid var(--border); border-radius: 8px; padding: 2px; background: var(--surface); }
.btn.ghost-danger { background: var(--surface); border-color: var(--danger); color: var(--danger); }
.link { border: none; background: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 13px; }
.link.danger { color: var(--danger); }
.small { font-size: 13px; padding: 6px 12px; }
.err { color: var(--danger); margin: 0; }
</style>
