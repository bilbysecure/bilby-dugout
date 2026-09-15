<script setup>
import { ref, reactive, watch } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { FormConfigApi, UploadApi } from '@/api/endpoints';

const qc = useQueryClient();
const sub = ref('service'); // service | tones
const busy = ref(false);

// ── data ──────────────────────────────────────────────────────
const { data: svcData } = useQuery({ queryKey: ['form-config', 'service_types'], queryFn: () => FormConfigApi.get('service_types') });
const { data: toneData } = useQuery({ queryKey: ['form-config', 'tones_of_voice'], queryFn: () => FormConfigApi.get('tones_of_voice') });
const svcList = ref([]);
const toneList = ref([]);
watch(svcData, (v) => { if (v) svcList.value = JSON.parse(JSON.stringify(v)); }, { immediate: true });
watch(toneData, (v) => { if (v) toneList.value = JSON.parse(JSON.stringify(v)); }, { immediate: true });

async function persistSvc() { busy.value = true; try { svcList.value = await FormConfigApi.save('service_types', svcList.value); qc.invalidateQueries({ queryKey: ['form-config', 'service_types'] }); } finally { busy.value = false; } }
async function persistTones() { busy.value = true; try { toneList.value = await FormConfigApi.save('tones_of_voice', toneList.value); qc.invalidateQueries({ queryKey: ['form-config', 'tones_of_voice'] }); } finally { busy.value = false; } }
async function resetSvc() { if (!confirm('Reset service types to defaults?')) return; busy.value = true; try { svcList.value = await FormConfigApi.reset('service_types'); } finally { busy.value = false; } }

const iconIsUrl = (i) => typeof i === 'string' && i.startsWith('http');

// ── editor ────────────────────────────────────────────────────
const editorOpen = ref(false);
const editIndex = ref(null);
const draft = reactive({ label: '', description: '', sub_types: [], platforms: [], objectives: [] });
const newSub = ref('');
const plat = reactive({ name: '', icon: '' });
const obj = reactive({ name: '', icon: '' });

function openAdd() { Object.assign(draft, { label: '', description: '', sub_types: [], platforms: [], objectives: [] }); editIndex.value = null; editorOpen.value = true; }
function openEdit(i) { if (svcList.value[i]?.locked) return; Object.assign(draft, JSON.parse(JSON.stringify(svcList.value[i]))); draft.platforms = draft.platforms || []; draft.objectives = draft.objectives || []; editIndex.value = i; editorOpen.value = true; }
function cancelEditor() { editorOpen.value = false; }
function addSub() { const v = newSub.value.trim(); if (v) draft.sub_types.push(v); newSub.value = ''; }
function addPlat() { const v = plat.name.trim(); if (v) draft.platforms.push({ name: v, icon: plat.icon }); plat.name = ''; plat.icon = ''; }
function addObj() { const v = obj.name.trim(); if (v) draft.objectives.push({ name: v, icon: obj.icon }); obj.name = ''; obj.icon = ''; }
async function uploadIcon(e, target) { const f = e.target.files?.[0]; if (!f) return; target.icon = await UploadApi.image(f); }

async function saveEditor() {
  if (!draft.label.trim()) return;
  const clone = JSON.parse(JSON.stringify(draft));
  if (editIndex.value == null) svcList.value.push(clone); else svcList.value[editIndex.value] = clone;
  await persistSvc();
  editorOpen.value = false;
}
async function deleteSvc(i) { if (svcList.value[i]?.locked) return; if (!confirm('Delete this service type?')) return; svcList.value.splice(i, 1); await persistSvc(); }

// tones
const newTone = ref('');
async function addTone() { const v = newTone.value.trim(); if (v) { toneList.value.push({ label: v }); newTone.value = ''; await persistTones(); } }
async function removeTone(i) { toneList.value.splice(i, 1); await persistTones(); }
</script>

<template>
  <div class="stack">
    <p class="muted" style="margin: 0;">Customise the options clients see when submitting a request. Changes take effect immediately.</p>

    <div class="subtabs">
      <button class="stab" :class="{ on: sub === 'service' }" @click="sub = 'service'">Service Types</button>
      <button class="stab" :class="{ on: sub === 'tones' }" @click="sub = 'tones'">Tones of Voice</button>
    </div>

    <!-- ══ Service Types ══ -->
    <div v-if="sub === 'service'" class="card panel">
      <div class="row" style="justify-content: space-between; align-items: flex-start;">
        <div>
          <strong>Service Types</strong>
          <p class="muted" style="margin: 2px 0 0; font-size: 13px;">The service cards shown on the request form. Clients can only see types included in their subscription.</p>
        </div>
        <div class="row" style="gap: 8px;">
          <button class="btn secondary" :disabled="busy" @click="resetSvc">↺ Reset to defaults</button>
          <button class="btn" @click="openAdd">＋ Add</button>
        </div>
      </div>

      <!-- Editor -->
      <div v-if="editorOpen" class="editor">
        <div class="editor-title">{{ editIndex == null ? 'New Service Type' : 'Edit Service Type' }}</div>
        <div class="grid2">
          <input v-model="draft.label" class="input" placeholder="Label *" />
          <input v-model="draft.description" class="input" placeholder="Description" />
        </div>

        <div class="field">
          <span class="flabel">Sub-types / formats</span>
          <div class="chips">
            <span v-for="(s, i) in draft.sub_types" :key="i" class="chip">{{ s }} <button class="x" @click="draft.sub_types.splice(i, 1)">✕</button></span>
          </div>
          <div class="addrow">
            <input v-model="newSub" class="input" placeholder="Add subtype…" @keyup.enter="addSub" />
            <button class="btn secondary" @click="addSub">Add</button>
          </div>
        </div>

        <div class="subpanel">
          <strong>Platforms</strong>
          <p class="muted hint">Add a short icon or emoji to make choices easier to scan.</p>
          <div class="chips">
            <span v-for="(p, i) in draft.platforms" :key="i" class="chip blue">
              <img v-if="iconIsUrl(p.icon)" :src="p.icon" class="ci" /><span v-else class="ci">{{ p.icon || '✦' }}</span>
              {{ p.name }} <button class="x" @click="draft.platforms.splice(i, 1)">✕</button>
            </span>
          </div>
          <div class="addrow">
            <span class="iconbox"><img v-if="iconIsUrl(plat.icon)" :src="plat.icon" class="ci" /><span v-else>{{ plat.icon || '🖼️' }}</span></span>
            <label class="btn secondary small">⬆ Upload<input type="file" accept="image/*" hidden @change="(e) => uploadIcon(e, plat)" /></label>
            <input v-model="plat.name" class="input" placeholder="Add platform…" @keyup.enter="addPlat" />
            <button class="btn secondary" @click="addPlat">Add</button>
          </div>
          <p class="muted note">Recommended icon: 32×32 px display size, upload at 64×64 px, PNG/WebP/SVG, ideally under 20 KB and max 50 KB.</p>
        </div>

        <div class="subpanel">
          <strong>Objectives</strong>
          <p class="muted hint">Use icons to make goals more visual for clients.</p>
          <div class="chips">
            <span v-for="(o, i) in draft.objectives" :key="i" class="chip blue">
              <img v-if="iconIsUrl(o.icon)" :src="o.icon" class="ci" /><span v-else class="ci">{{ o.icon || '✦' }}</span>
              {{ o.name }} <button class="x" @click="draft.objectives.splice(i, 1)">✕</button>
            </span>
          </div>
          <div class="addrow">
            <span class="iconbox"><img v-if="iconIsUrl(obj.icon)" :src="obj.icon" class="ci" /><span v-else>{{ obj.icon || '🖼️' }}</span></span>
            <label class="btn secondary small">⬆ Upload<input type="file" accept="image/*" hidden @change="(e) => uploadIcon(e, obj)" /></label>
            <input v-model="obj.name" class="input" placeholder="Add objective…" @keyup.enter="addObj" />
            <button class="btn secondary" @click="addObj">Add</button>
          </div>
          <p class="muted note">Recommended icon: 32×32 px display size, upload at 64×64 px, PNG/WebP/SVG, ideally under 20 KB and max 50 KB.</p>
        </div>

        <div class="row" style="gap: 12px;">
          <button class="btn" :disabled="!draft.label || busy" @click="saveEditor">{{ editIndex == null ? 'Add Service' : '✓ Save' }}</button>
          <button class="link" @click="cancelEditor">Cancel</button>
        </div>
      </div>

      <!-- Cards -->
      <template v-else>
        <div v-for="(s, i) in svcList" :key="i" class="svc card" :class="{ locked: s.locked }">
          <span class="handle">⠿</span>
          <div v-if="!s.locked" class="svc-actions">
            <button class="ic edit" title="Edit" @click="openEdit(i)">✎</button>
            <button class="ic del" title="Delete" @click="deleteSvc(i)">🗑</button>
          </div>
          <span v-else class="lock-badge">🔒 Default</span>
          <div class="svc-body">
            <strong>{{ s.label }}</strong>
            <p class="muted desc">{{ s.description }}</p>
            <div class="chips">
              <span v-for="(t, j) in s.sub_types" :key="j" class="tag">{{ t }}</span>
            </div>
            <div v-if="s.platforms?.length || s.objectives?.length" class="chips" style="margin-top: 8px;">
              <span v-for="(p, j) in s.platforms" :key="'p' + j" class="chip blue"><span class="ci">✦</span> {{ p.name }}</span>
              <span v-for="(o, j) in s.objectives" :key="'o' + j" class="chip blue"><span class="ci">✦</span> {{ o.name }}</span>
            </div>
          </div>
        </div>
        <p v-if="!svcList.length" class="muted">No service types. Click Add or Reset to defaults.</p>
      </template>
    </div>

    <!-- ══ Tones of Voice ══ -->
    <div v-else class="card panel">
      <strong>Tones of Voice</strong>
      <p class="muted" style="margin: 2px 0 10px; font-size: 13px;">The tone options clients can choose on the request form.</p>
      <div class="chips">
        <span v-for="(t, i) in toneList" :key="i" class="chip blue">{{ t.label }} <button class="x" @click="removeTone(i)">✕</button></span>
      </div>
      <div class="addrow" style="margin-top: 10px;">
        <input v-model="newTone" class="input" placeholder="Add tone…" @keyup.enter="addTone" style="max-width: 280px;" />
        <button class="btn secondary" @click="addTone">Add</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.subtabs { display: flex; gap: 6px; }
.stab { border: none; background: none; padding: 7px 14px; border-radius: var(--radius); font-size: 14px; color: var(--muted); }
.stab.on { background: var(--surface); border: 1px solid var(--border); color: var(--text); font-weight: 600; }
.panel { padding: 18px; display: flex; flex-direction: column; gap: 14px; background: var(--surface); }

.svc { position: relative; padding: 16px 16px 16px 34px; }
.svc.locked { background: #fbfbfc; }
.lock-badge { position: absolute; top: 14px; right: 14px; font-size: 11px; color: var(--muted); background: #eef0f3; padding: 2px 8px; border-radius: 999px; }
.handle { position: absolute; left: 12px; top: 18px; color: var(--border); cursor: grab; }
.svc-actions { position: absolute; top: 12px; right: 12px; display: none; gap: 6px; }
.svc:hover .svc-actions { display: flex; }
.ic { border: 1px solid var(--border); background: var(--surface); border-radius: 8px; width: 30px; height: 30px; cursor: pointer; }
.ic.edit { color: var(--primary); }
.ic.del { color: var(--danger); }
.svc-body strong { font-size: 15px; }
.desc { margin: 3px 0 10px; font-size: 13px; }

.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.tag { background: #f4f2ef; color: #6b7480; font-size: 12px; padding: 3px 9px; border-radius: 6px; }
.chip { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); background: var(--surface); font-size: 13px; padding: 4px 10px; border-radius: 999px; }
.chip.blue { background: #eef2fb; border-color: #dbe4f7; color: var(--primary); }
.ci { width: 16px; height: 16px; display: inline-grid; place-items: center; font-size: 12px; }
img.ci { object-fit: contain; border-radius: 3px; }
.x { border: none; background: none; cursor: pointer; color: inherit; opacity: .6; font-size: 11px; }

.editor { border: 1px solid #c7d2fe; background: #f7f8ff; border-radius: var(--radius); padding: 18px; display: flex; flex-direction: column; gap: 14px; }
.editor-title { color: var(--primary); font-weight: 700; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field .flabel, .subpanel strong { font-size: 13px; }
.flabel { display: block; margin-bottom: 6px; color: var(--muted); }
.addrow { display: flex; gap: 8px; align-items: center; margin-top: 8px; }
.addrow .input { flex: 1; }
.subpanel { border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; background: var(--surface); }
.hint { margin: 2px 0 8px; font-size: 12px; }
.note { font-size: 11px; margin: 6px 0 0; }
.iconbox { width: 40px; height: 34px; border: 1px solid var(--border); border-radius: 8px; display: grid; place-items: center; background: var(--surface); }
.iconbox img { width: 24px; height: 24px; object-fit: contain; }
.small { font-size: 13px; padding: 6px 10px; }
.link { border: none; background: none; color: var(--primary); cursor: pointer; }
</style>
