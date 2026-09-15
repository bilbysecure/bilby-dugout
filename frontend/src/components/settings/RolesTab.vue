<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';

const props = defineProps({
  kind: String,
  queryKey: String,
  api: Object,
  groups: Array,   // [{ group, items: [{key,label}] }]
  title: String,   // "Staff role"
  hint: String,
});

const qc = useQueryClient();
const { data, isLoading } = useQuery({ queryKey: [props.queryKey], queryFn: () => props.api.list() });
const roles = computed(() => data.value ?? []);

const editingId = ref(null);
const open = ref(false);
const error = ref('');
const form = reactive({ name: '', description: '', status: 'active', permissions: [] });

function openNew() { Object.assign(form, { name: '', description: '', status: 'active', permissions: [] }); editingId.value = null; open.value = true; error.value = ''; }
function openEdit(r) { Object.assign(form, { name: r.name, description: r.description || '', status: r.status, permissions: [...(r.permissions || [])] }); editingId.value = r.id; open.value = true; error.value = ''; }
function close() { open.value = false; }
function toggle(key) { const i = form.permissions.indexOf(key); if (i === -1) form.permissions.push(key); else form.permissions.splice(i, 1); }

const invalidate = () => qc.invalidateQueries({ queryKey: [props.queryKey] });
const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => (editingId.value ? props.api.update(editingId.value, { ...form }) : props.api.create({ ...form })),
  onSuccess: () => { invalidate(); close(); },
  onError: (e) => { error.value = e?.response?.data?.error?.message || 'Could not save.'; },
});
const { mutate: remove } = useMutation({ mutationFn: (id) => props.api.remove(id), onSuccess: invalidate });
</script>

<template>
  <div class="stack">
    <div class="row" style="justify-content: space-between;">
      <p class="muted" style="margin: 0;">{{ hint }}</p>
      <button class="btn" @click="openNew">＋ Add {{ title.toLowerCase() }}</button>
    </div>

    <div class="card">
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>Name</th><th>Permissions</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <tr v-for="r in roles" :key="r.id">
            <td><strong>{{ r.name }}</strong><div class="muted" style="font-size: 12px;">{{ r.description }}</div></td>
            <td class="muted">{{ (r.permissions || []).length }} permission(s)</td>
            <td><span class="badge" :class="{ off: r.status !== 'active' }">{{ r.status }}</span></td>
            <td class="row" style="gap: 6px;">
              <button class="link" @click="openEdit(r)">Edit</button>
              <button class="link danger" @click="remove(r.id)">Delete</button>
            </td>
          </tr>
          <tr v-if="!roles.length"><td colspan="4" class="muted">No {{ title.toLowerCase() }}s yet.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="open" class="overlay" @click.self="close">
      <div class="modal card stack">
        <div class="row" style="justify-content: space-between;">
          <h3 style="margin: 0;">{{ editingId ? 'Edit' : 'New' }} {{ title.toLowerCase() }}</h3>
          <button class="icon-btn" @click="close">✕</button>
        </div>
        <div class="grid2">
          <label class="stack"><span>Name *</span><input v-model="form.name" class="input" :placeholder="title === 'Staff role' ? 'e.g. BilbyPixel Team Leader' : 'e.g. Marketing Manager'" /></label>
          <label class="stack"><span>Status</span>
            <select v-model="form.status" class="input"><option value="active">Active</option><option value="inactive">Inactive</option></select>
          </label>
        </div>
        <label class="stack"><span>Description</span><input v-model="form.description" class="input" /></label>

        <span class="lbl">Permissions</span>
        <div v-for="g in groups" :key="g.group" class="pgroup">
          <div class="gtitle">{{ g.group }}</div>
          <div class="perms">
            <label v-for="it in g.items" :key="it.key" class="perm" :class="{ on: form.permissions.includes(it.key) }">
              <input type="checkbox" :checked="form.permissions.includes(it.key)" @change="toggle(it.key)" />
              {{ it.label }}
            </label>
          </div>
        </div>

        <p v-if="error" class="err">{{ error }}</p>
        <div class="row" style="justify-content: flex-end; gap: 8px;">
          <button class="btn secondary" @click="close">Cancel</button>
          <button class="btn" :disabled="!form.name || saving" @click="save()">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: top; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.badge.off { background: #eee; color: var(--muted); }
.link { border: none; background: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 13px; }
.link.danger { color: var(--danger); }
.overlay { position: fixed; inset: 0; background: rgba(20,28,40,.45); display: grid; place-items: center; z-index: 50; padding: 20px; }
.modal { width: min(640px, 96vw); max-height: 92vh; overflow: auto; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label.stack span { font-size: 12px; color: var(--muted); }
.lbl { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; }
.pgroup { margin-bottom: 6px; }
.gtitle { font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
.perms { display: flex; flex-wrap: wrap; gap: 6px; }
.perm { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); border-radius: 999px; padding: 4px 10px; font-size: 13px; cursor: pointer; }
.perm.on { border-color: var(--primary); background: #eef2f8; color: var(--primary); }
.icon-btn { border: none; background: none; font-size: 16px; color: var(--muted); cursor: pointer; }
.err { color: var(--danger); margin: 0; }
</style>
