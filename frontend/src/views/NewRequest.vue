<script setup>
import { reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { RequestsApi, AssistantApi, AdminApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';
import { SERVICE_TYPES, PLATFORMS, OBJECTIVES, TONES, PRIORITIES } from '@/constants/options';

const auth = useAuthStore();
const router = useRouter();
const qc = useQueryClient();

const form = reactive({
  client_email: '', type: '', sub_type: '', platform: [], objective: '',
  title: '', description: '', target_audience: '', key_messaging: '',
  tone: '', priority: 'normal', due_date: '', publish_date: '', notes: '',
});

// Steps — agency picks a client first; clients create for their own company.
const allSteps = [
  { key: 'client', label: 'Client' },
  { key: 'service', label: 'Service Type' },
  { key: 'platform', label: 'Platform & Goal' },
  { key: 'brief', label: 'Creative Brief' },
  { key: 'schedule', label: 'Schedule' },
];
const steps = computed(() => (auth.isAgency ? allSteps : allSteps.filter((s) => s.key !== 'client')));
const step = ref(0);
const currentKey = computed(() => steps.value[step.value]?.key);
const isLast = computed(() => step.value === steps.value.length - 1);

// ── Client picker (agency) ────────────────────────────────────
const { data: subData } = useQuery({ queryKey: ['subscriptions'], queryFn: () => AdminApi.subscriptions(), enabled: auth.isAgency });
const clients = computed(() => (subData.value ?? []).map((s) => ({
  email: s.client_email, name: s.client_name || s.company_name || s.client_email,
  company: s.company_name || '', plan: s.plan_name || '',
})));
const clientSearch = ref('');
const filteredClients = computed(() => {
  const q = clientSearch.value.trim().toLowerCase();
  return clients.value.filter((c) => !q || `${c.name} ${c.email} ${c.company}`.toLowerCase().includes(q));
});
const initial = (n) => (n || '?').trim().charAt(0).toUpperCase();

function togglePlatform(p) { const i = form.platform.indexOf(p); if (i === -1) form.platform.push(p); else form.platform.splice(i, 1); }

const canNext = computed(() => {
  if (currentKey.value === 'client') return !!form.client_email;
  if (currentKey.value === 'service') return !!form.type;
  if (currentKey.value === 'brief') return !!form.title;
  return true;
});
function next() { if (canNext.value && !isLast.value) step.value++; }
function back() { if (step.value > 0) step.value--; }

// ── AI drafting ───────────────────────────────────────────────
const aiBusy = ref(false);
async function draftWithAi() {
  aiBusy.value = true;
  try {
    const d = await AssistantApi.draftRequest({
      type: form.type, platform: form.platform, objective: form.objective,
      tone: form.tone, notes: form.notes, title: form.title, target_audience: form.target_audience,
    });
    if (d.title && !form.title) form.title = d.title;
    form.description = d.description || form.description;
    form.target_audience = d.target_audience || form.target_audience;
    form.key_messaging = d.key_messaging || form.key_messaging;
    if (d.tone) form.tone = d.tone;
  } finally { aiBusy.value = false; }
}
async function draftAgent() {
  await draftWithAi();
  const idx = steps.value.findIndex((s) => s.key === 'brief');
  if (idx >= 0) step.value = idx;
}

// ── Local drafts ──────────────────────────────────────────────
const DRAFTS_KEY = 'bilbydugout.drafts';
const drafts = ref([]);
function loadDrafts() { try { drafts.value = JSON.parse(localStorage.getItem(DRAFTS_KEY) || '[]'); } catch { drafts.value = []; } }
function persist() { localStorage.setItem(DRAFTS_KEY, JSON.stringify(drafts.value)); }
loadDrafts();
const humanize = (s) => String(s || '').replaceAll('_', ' ');
function saveDraft() {
  const name = form.title || (form.type ? humanize(form.type) : 'Untitled request');
  drafts.value.unshift({ id: Date.now(), name, savedAt: new Date().toISOString(), form: JSON.parse(JSON.stringify(form)) });
  drafts.value = drafts.value.slice(0, 20);
  persist();
}
function loadDraft(d) { Object.assign(form, d.form); step.value = 0; }
function deleteDraft(id) { drafts.value = drafts.value.filter((d) => d.id !== id); persist(); }
const showDrafts = ref(true);
const draftTime = (iso) => new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

// ── Submit ────────────────────────────────────────────────────
const { mutate: submit, isPending, error } = useMutation({
  mutationFn: () => {
    const payload = {
      ...form,
      sub_type: form.sub_type ? form.sub_type.split(',').map((s) => s.trim()).filter(Boolean) : [],
      publish_date: form.publish_date || null, due_date: form.due_date || null,
    };
    if (auth.impersonation) payload.client_email = auth.impersonation.email; // create for the client we're viewing as
    else if (!auth.isAgency) delete payload.client_email;
    return RequestsApi.create(payload);
  },
  onSuccess: (created) => { qc.invalidateQueries({ queryKey: ['requests'] }); router.push({ name: 'request-detail', params: { id: created.id } }); },
});
const errorMessage = computed(() => error.value?.response?.data?.error?.message || (error.value ? 'Could not create the request.' : ''));
</script>

<template>
  <div v-if="auth.canCreateRequests" class="page">
    <div class="main">
      <button class="back" @click="router.back()">← Back</button>
      <header class="head">
        <div>
          <h2>New Request</h2>
          <p class="muted">Tell us what you need — we'll take it from here.</p>
        </div>
        <div class="head-actions">
          <button class="btn secondary" :disabled="aiBusy" @click="draftAgent">{{ aiBusy ? 'Drafting…' : '🪄 Draft Agent' }}</button>
          <button class="btn secondary" @click="showDrafts = !showDrafts">Drafts<span v-if="drafts.length" class="cnt">{{ drafts.length }}</span></button>
          <button class="btn" @click="saveDraft">💾 Save</button>
        </div>
      </header>

      <!-- Step indicator -->
      <div class="stepper">
        <template v-for="(s, i) in steps" :key="s.key">
          <div class="step" :class="{ active: i === step, done: i < step }">
            <span class="circle"><template v-if="i < step">✓</template><template v-else>{{ i + 1 }}</template></span>
            <span class="slabel">{{ s.label }}</span>
          </div>
          <div v-if="i < steps.length - 1" class="connector" :class="{ done: i < step }" />
        </template>
      </div>

      <div class="content">
        <!-- Client -->
        <template v-if="currentKey === 'client'">
          <p class="lead">Select the client you're creating this request for.</p>
          <div class="search"><span class="ico">🔍</span><input v-model="clientSearch" class="input" placeholder="Search by name, email or company…" /></div>
          <button
            v-for="c in filteredClients" :key="c.email" type="button"
            class="client" :class="{ sel: form.client_email === c.email }" @click="form.client_email = c.email"
          >
            <span class="avatar">{{ initial(c.name) }}</span>
            <span class="c-body">
              <strong>{{ c.name }}</strong>
              <span class="muted">{{ c.company }}<template v-if="c.plan"> · {{ c.plan }}</template></span>
            </span>
          </button>
          <p v-if="!filteredClients.length" class="muted">No clients found.</p>
        </template>

        <!-- Service type -->
        <template v-else-if="currentKey === 'service'">
          <p class="lead">What kind of work do you need?</p>
          <div class="choices">
            <button v-for="t in SERVICE_TYPES" :key="t.value" type="button" class="choice" :class="{ sel: form.type === t.value }" @click="form.type = t.value">{{ t.label }}</button>
          </div>
          <label class="stack"><span>Formats (optional, comma-separated)</span><input v-model="form.sub_type" class="input" placeholder="reel, story, carousel" /></label>
        </template>

        <!-- Platform & goal -->
        <template v-else-if="currentKey === 'platform'">
          <p class="lead">Where will it go, and what's the goal?</p>
          <span class="muted">Platforms</span>
          <div class="choices">
            <button v-for="p in PLATFORMS" :key="p" type="button" class="choice" :class="{ sel: form.platform.includes(p) }" @click="togglePlatform(p)">{{ p }}</button>
          </div>
          <label class="stack"><span>Objective</span>
            <select v-model="form.objective" class="input"><option value="">—</option><option v-for="o in OBJECTIVES" :key="o.value" :value="o.value">{{ o.label }}</option></select>
          </label>
        </template>

        <!-- Creative brief -->
        <template v-else-if="currentKey === 'brief'">
          <div class="row" style="justify-content: space-between; align-items: center;">
            <span class="muted" style="font-size: 13px;">Let the assistant draft the brief from what you've entered.</span>
            <button type="button" class="btn secondary" :disabled="aiBusy" @click="draftWithAi">{{ aiBusy ? 'Drafting…' : '✨ Draft with AI' }}</button>
          </div>
          <label class="stack"><span>Title *</span><input v-model="form.title" class="input" /></label>
          <label class="stack"><span>Description</span><textarea v-model="form.description" class="input" rows="3" /></label>
          <label class="stack"><span>Target audience</span><input v-model="form.target_audience" class="input" /></label>
          <label class="stack"><span>Key messaging</span><textarea v-model="form.key_messaging" class="input" rows="2" /></label>
          <div class="row">
            <label class="stack" style="flex:1;"><span>Tone</span>
              <select v-model="form.tone" class="input"><option value="">—</option><option v-for="t in TONES" :key="t" :value="t">{{ t }}</option></select></label>
            <label class="stack" style="flex:1;"><span>Priority</span>
              <select v-model="form.priority" class="input"><option v-for="p in PRIORITIES" :key="p" :value="p">{{ p }}</option></select></label>
          </div>
        </template>

        <!-- Schedule -->
        <template v-else>
          <p class="lead">When is it needed?</p>
          <div class="row">
            <label class="stack" style="flex:1;"><span>Due date</span><input v-model="form.due_date" class="input" type="date" /></label>
            <label class="stack" style="flex:1;"><span>Publish date</span><input v-model="form.publish_date" class="input" type="datetime-local" /></label>
          </div>
          <label class="stack"><span>Notes</span><textarea v-model="form.notes" class="input" rows="2" /></label>
        </template>

        <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
      </div>

      <!-- Footer -->
      <footer class="foot">
        <button class="btn secondary" :disabled="step === 0" @click="back">← Previous</button>
        <span class="muted">Step {{ step + 1 }} of {{ steps.length }}</span>
        <button v-if="!isLast" class="btn" :disabled="!canNext" @click="next">Next →</button>
        <button v-else class="btn" :disabled="!form.title || !form.type || isPending" @click="submit()">{{ isPending ? 'Submitting…' : 'Submit request' }}</button>
      </footer>
    </div>

    <!-- Recent drafts rail -->
    <aside v-if="showDrafts" class="rail">
      <div class="rail-title">Recent Drafts</div>
      <p v-if="!drafts.length" class="empty muted">🕘<br />No drafts yet</p>
      <div v-for="d in drafts" :key="d.id" class="draft" @click="loadDraft(d)">
        <div>
          <strong>{{ d.name }}</strong>
          <div class="muted" style="font-size: 11px;">{{ draftTime(d.savedAt) }}</div>
        </div>
        <button class="x" @click.stop="deleteDraft(d.id)">✕</button>
      </div>
    </aside>
  </div>

  <section v-else class="card">
    <p class="muted">Your role can't create requests. Ask an operations manager or your company owner.</p>
  </section>
</template>

<style scoped>
.page { display: grid; grid-template-columns: 1fr 220px; gap: 28px; align-items: start; }
.main { max-width: 760px; }
.back { border: none; background: none; color: var(--muted); cursor: pointer; font-size: 14px; margin-bottom: 8px; }
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 20px; }
.head h2 { margin: 0; }
.head-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.cnt { background: #eef2f8; color: var(--primary); border-radius: 999px; font-size: 11px; padding: 1px 6px; margin-left: 6px; }

.stepper { display: flex; align-items: center; margin-bottom: 24px; }
.step { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: none; }
.circle { width: 34px; height: 34px; border-radius: 50%; border: 2px solid var(--border); display: grid; place-items: center; font-size: 14px; color: var(--muted); background: var(--surface); }
.slabel { font-size: 12px; color: var(--muted); white-space: nowrap; }
.step.active .circle { border-color: var(--primary); color: var(--primary); }
.step.active .slabel { color: var(--primary); font-weight: 600; }
.step.done .circle { background: var(--primary); border-color: var(--primary); color: #fff; }
.connector { flex: 1; height: 2px; background: var(--border); margin: 0 6px; margin-bottom: 22px; }
.connector.done { background: var(--primary); }

.content { display: flex; flex-direction: column; gap: 14px; min-height: 260px; }
.lead { font-size: 15px; color: var(--text); margin: 0; }
.search { position: relative; }
.search .ico { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); opacity: .5; }
.search .input { padding-left: 34px; }

.client { display: flex; align-items: center; gap: 12px; text-align: left; width: 100%; border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); padding: 12px 14px; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
.client:hover { border-color: var(--primary); }
.client.sel { border-color: var(--primary); box-shadow: 0 0 0 2px #eef2f8; }
.avatar { width: 40px; height: 40px; border-radius: 50%; background: #eef2f8; color: var(--primary); display: grid; place-items: center; font-weight: 700; flex: none; }
.c-body { display: flex; flex-direction: column; }
.c-body .muted { font-size: 13px; }

.choices { display: flex; flex-wrap: wrap; gap: 8px; }
.choice { border: 1px solid var(--border); background: var(--surface); padding: 7px 12px; border-radius: 999px; text-transform: capitalize; }
.choice.sel { border-color: var(--primary); background: #eef2f8; color: var(--primary); }
label.stack span { font-size: 13px; color: var(--muted); }

.foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid var(--border); padding-top: 16px; margin-top: 20px; }
.error { color: var(--danger); margin: 0; }

.rail { padding-top: 40px; }
.rail-title { font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 12px; }
.empty { text-align: center; font-size: 13px; line-height: 1.8; }
.draft { display: flex; justify-content: space-between; align-items: center; gap: 8px; border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); padding: 9px 11px; margin-bottom: 8px; cursor: pointer; }
.draft:hover { border-color: var(--primary); }
.draft strong { font-size: 13px; }
.x { border: none; background: none; color: var(--muted); cursor: pointer; font-size: 12px; }
@media (max-width: 820px) { .page { grid-template-columns: 1fr; } .rail { padding-top: 0; } }
</style>
