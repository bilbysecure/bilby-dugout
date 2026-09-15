<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { TeamApi, DesignationsApi, StaffRolesApi, UploadApi } from '@/api/endpoints';
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue';

const qc = useQueryClient();
const { data: membersData, isLoading } = useQuery({ queryKey: ['team-members'], queryFn: () => TeamApi.list() });
const { data: desigData } = useQuery({ queryKey: ['designations'], queryFn: () => DesignationsApi.list() });
const { data: rolesData } = useQuery({ queryKey: ['staff-roles'], queryFn: () => StaffRolesApi.list() });

const members = computed(() => membersData.value ?? []);
const designations = computed(() => (desigData.value ?? []).filter((d) => d.status === 'active'));
const roles = computed(() => rolesData.value ?? []);

const editing = ref(null);       // null = closed, {} = new, {...} = edit
const uploading = ref(false);
const error = ref('');
const blank = () => ({ full_name: '', email: '', phone: '', designation: '', staff_role_slug: '', department: '', status: 'active', bio: '', avatar_url: '', is_admin: false });
const form = reactive(blank());

function openNew() { Object.assign(form, blank()); editing.value = {}; error.value = ''; }
function openEdit(m) { Object.assign(form, blank(), m); editing.value = m; error.value = ''; }
function close() { editing.value = null; }

async function uploadPhoto(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  uploading.value = true;
  try { form.avatar_url = await UploadApi.image(file); }
  catch (err) { error.value = err?.response?.data?.error?.message || 'Upload failed'; }
  finally { uploading.value = false; }
}

const invalidate = () => qc.invalidateQueries({ queryKey: ['team-members'] });
const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => (editing.value?.id ? TeamApi.update(editing.value.id, { ...form }) : TeamApi.create({ ...form })),
  onSuccess: () => { invalidate(); close(); },
  onError: (e) => { error.value = e?.response?.data?.error?.message || 'Could not save.'; },
});
const { mutate: removeMember } = useMutation({ mutationFn: (id) => TeamApi.remove(id), onSuccess: invalidate });

const fmt = (s) => (s ? String(s).replaceAll('_', ' ') : '—');
const initials = (n) => (n || '?').split(' ').map((x) => x[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
  <div class="stack">
    <div class="row" style="justify-content: flex-end;">
      <button class="btn" @click="openNew">＋ Add staff</button>
    </div>

    <div class="card">
      <p v-if="isLoading" class="muted">Loading…</p>
      <table v-else class="tbl">
        <thead><tr><th></th><th>Name</th><th>Email</th><th>Designation</th><th>Access role</th><th>Dept</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <tr v-for="m in members" :key="m.id">
            <td>
              <span class="avatar">
                <img v-if="m.avatar_url" :src="m.avatar_url" alt="" />
                <span v-else>{{ initials(m.full_name) }}</span>
              </span>
            </td>
            <td>{{ m.full_name }} <span v-if="m.is_admin" class="badge admin">Admin</span></td>
            <td class="muted">{{ m.email || '—' }}</td>
            <td><span class="badge">{{ fmt(m.designation) }}</span></td>
            <td class="muted">{{ fmt(m.staff_role_slug) || '—' }}</td>
            <td class="muted">{{ m.department || '—' }}</td>
            <td><span class="badge" :class="{ off: m.status !== 'active' }">{{ m.status }}</span></td>
            <td class="row" style="gap: 6px;">
              <button class="link" @click="openEdit(m)">Edit</button>
              <button class="link danger" @click="removeMember(m.id)">Delete</button>
            </td>
          </tr>
          <tr v-if="!members.length"><td colspan="8" class="muted">No staff yet.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Add / edit modal -->
    <div v-if="editing" class="overlay" @click.self="close">
      <div class="modal card stack">
        <div class="row" style="justify-content: space-between;">
          <h3 style="margin: 0;">{{ editing.id ? 'Edit staff' : 'Add staff' }}</h3>
          <button class="icon-btn" @click="close">✕</button>
        </div>

        <div class="row" style="gap: 16px; align-items: center;">
          <span class="avatar lg">
            <img v-if="form.avatar_url" :src="form.avatar_url" alt="" />
            <span v-else>{{ initials(form.full_name) }}</span>
          </span>
          <label class="btn secondary small">
            {{ uploading ? 'Uploading…' : 'Upload photo' }}
            <input type="file" accept="image/*" hidden @change="uploadPhoto" />
          </label>
          <button v-if="form.avatar_url" class="link" @click="form.avatar_url = ''">Remove</button>
        </div>

        <div class="grid2">
          <label class="stack"><span>Full name *</span><input v-model="form.full_name" class="input" /></label>
          <label class="stack"><span>Email</span><input v-model="form.email" class="input" type="email" /></label>
          <label class="stack"><span>Phone</span><input v-model="form.phone" class="input" /></label>
          <label class="stack"><span>Department</span><input v-model="form.department" class="input" /></label>
          <label class="stack">
            <span>Designation</span>
            <select v-model="form.designation" class="input">
              <option value="">—</option>
              <option v-for="d in designations" :key="d.id" :value="d.slug">{{ d.name }}</option>
            </select>
          </label>
          <label class="stack">
            <span>Access role</span>
            <select v-model="form.staff_role_slug" class="input">
              <option value="">—</option>
              <option v-for="r in roles" :key="r.id" :value="r.slug">{{ r.name }}</option>
            </select>
          </label>
          <label class="stack">
            <span>Status</span>
            <select v-model="form.status" class="input"><option value="active">Active</option><option value="inactive">Inactive</option></select>
          </label>
        </div>
        <label class="stack"><span>Bio / notes</span><textarea v-model="form.bio" class="input" rows="3" /></label>

        <div class="admin-toggle">
          <ToggleSwitch v-model="form.is_admin" />
          <div>
            <strong>Admin access</strong>
            <p class="muted">Grants full control (Global Admin) — Settings, Form Config, Service Plans, SLA Tiers and all client data. Independent of designation.</p>
          </div>
        </div>

        <p v-if="error" class="err">{{ error }}</p>
        <div class="row" style="justify-content: flex-end; gap: 8px;">
          <button class="btn secondary" @click="close">Cancel</button>
          <button class="btn" :disabled="!form.full_name || saving" @click="save()">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tbl { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 8px; border-bottom: 1px solid var(--border); font-size: 14px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: #eef2f8; color: var(--primary); display: grid; place-items: center; font-size: 12px; font-weight: 700; overflow: hidden; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar.lg { width: 64px; height: 64px; font-size: 20px; }
.badge.off { background: #eee; color: var(--muted); }
.badge.admin { background: #eef2fb; color: var(--primary); margin-left: 6px; }
.admin-toggle { display: flex; align-items: flex-start; gap: 12px; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg); }
.admin-toggle strong { font-size: 14px; }
.admin-toggle p { margin: 2px 0 0; font-size: 12px; }
.link { border: none; background: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 13px; }
.link.danger { color: var(--danger); }
.overlay { position: fixed; inset: 0; background: rgba(20,28,40,.45); display: grid; place-items: center; z-index: 50; padding: 20px; }
.modal { width: min(680px, 96vw); max-height: 92vh; overflow: auto; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label.stack span { font-size: 12px; color: var(--muted); }
.icon-btn { border: none; background: none; font-size: 16px; color: var(--muted); cursor: pointer; }
.small { font-size: 13px; padding: 6px 10px; }
.err { color: var(--danger); margin: 0; }
</style>
