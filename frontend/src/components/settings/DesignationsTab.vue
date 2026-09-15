<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { DesignationsApi } from '@/api/endpoints';

const qc = useQueryClient();
const { data, isLoading } = useQuery({ queryKey: ['designations'], queryFn: () => DesignationsApi.list() });
const rows = computed(() => data.value ?? []);

const editingId = ref(null);
const error = ref('');
const form = reactive({ name: '', description: '', status: 'active' });
const adding = ref(false);

function openNew() { Object.assign(form, { name: '', description: '', status: 'active' }); editingId.value = null; adding.value = true; error.value = ''; }
function openEdit(d) { Object.assign(form, { name: d.name, description: d.description || '', status: d.status }); editingId.value = d.id; adding.value = true; error.value = ''; }
function close() { adding.value = false; }

const invalidate = () => qc.invalidateQueries({ queryKey: ['designations'] });
const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => (editingId.value ? DesignationsApi.update(editingId.value, { ...form }) : DesignationsApi.create({ ...form })),
  onSuccess: () => { invalidate(); close(); },
  onError: (e) => { error.value = e?.response?.data?.error?.message || 'Could not save.'; },
});
const { mutate: remove } = useMutation({ mutationFn: (id) => DesignationsApi.remove(id), onSuccess: invalidate });
</script>

<template>
  <div class="stack">
    <div class="row" style="justify-content: space-between;">
      <p class="muted" style="margin: 0;">Job titles assignable to BilbyPixel staff.</p>
      <button class="btn" @click="openNew">＋ Add designation</button>
    </div>

    <div class="card">
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <tr v-for="d in rows" :key="d.id">
            <td>{{ d.name }}</td>
            <td class="muted"><code>{{ d.slug }}</code></td>
            <td class="muted">{{ d.description || '—' }}</td>
            <td><span class="badge" :class="{ off: d.status !== 'active' }">{{ d.status }}</span></td>
            <td class="row" style="gap: 6px;">
              <button class="link" @click="openEdit(d)">Edit</button>
              <button class="link danger" @click="remove(d.id)">Delete</button>
            </td>
          </tr>
          <tr v-if="!rows.length"><td colspan="5" class="muted">No designations yet.</td></tr>
        </tbody>
      </table>
    </div>

    <div v-if="adding" class="overlay" @click.self="close">
      <div class="modal card stack">
        <h3 style="margin: 0;">{{ editingId ? 'Edit designation' : 'Add designation' }}</h3>
        <label class="stack"><span>Name *</span><input v-model="form.name" class="input" placeholder="e.g. Motion Designer" /></label>
        <label class="stack"><span>Description</span><input v-model="form.description" class="input" /></label>
        <label class="stack"><span>Status</span>
          <select v-model="form.status" class="input"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </label>
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
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.badge.off { background: #eee; color: var(--muted); }
.link { border: none; background: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 13px; }
.link.danger { color: var(--danger); }
.overlay { position: fixed; inset: 0; background: rgba(20,28,40,.45); display: grid; place-items: center; z-index: 50; padding: 20px; }
.modal { width: min(460px, 96vw); }
label.stack span { font-size: 12px; color: var(--muted); }
.err { color: var(--danger); margin: 0; }
code { background: #f2f2f2; padding: 1px 5px; border-radius: 4px; font-size: 12px; }
</style>
