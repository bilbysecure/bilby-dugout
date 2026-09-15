<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { ClientMembersApi, ClientRolesApi, UploadApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({ client: { type: Object, required: true } });
const emit = defineEmits(['close']);

const qc = useQueryClient();
const auth = useAuthStore();
const router = useRouter();
const key = ['client-members', props.client.client_email];

const { data: memberData } = useQuery({ queryKey: key, queryFn: () => ClientMembersApi.list(props.client.client_email) });
const { data: roleData } = useQuery({ queryKey: ['client-roles'], queryFn: () => ClientRolesApi.list() });
const members = computed(() => memberData.value ?? []);
const roles = computed(() => roleData.value ?? []);

const editing = ref(null); // null | id | 'new'
const uploading = ref(false);
const error = ref('');
const blank = () => ({ full_name: '', email: '', role: '', client_role_slug: '', avatar_url: '', status: 'active' });
const form = reactive(blank());

function openAdd() { Object.assign(form, blank()); editing.value = 'new'; error.value = ''; }
function openEdit(m) { Object.assign(form, blank(), m); editing.value = m.id; error.value = ''; }
function close() { editing.value = null; }
async function uploadPhoto(e) { const f = e.target.files?.[0]; if (!f) return; uploading.value = true; try { form.avatar_url = await UploadApi.image(f); } finally { uploading.value = false; } }

const invalidate = () => qc.invalidateQueries({ queryKey: key });
const { mutate: save, isPending: saving } = useMutation({
  mutationFn: () => (editing.value === 'new'
    ? ClientMembersApi.create({ ...form, client_email: props.client.client_email, company_name: props.client.company_name })
    : ClientMembersApi.update(editing.value, { ...form })),
  onSuccess: () => { invalidate(); close(); },
  onError: (e) => { error.value = e?.response?.data?.error?.message || 'Could not save.'; },
});
const { mutate: remove } = useMutation({ mutationFn: (id) => ClientMembersApi.remove(id), onSuccess: invalidate });

function viewAs(m) {
  auth.impersonateClient(props.client.client_email, `${m.full_name} · ${props.client.company_name || props.client.client_email}`);
  qc.invalidateQueries();
  emit('close');
  router.push({ name: 'dashboard' });
}
const initial = (n) => (n || '?').trim().charAt(0).toUpperCase();
const isOwner = (m) => m.role && m.role.toLowerCase() === 'owner';
</script>

<template>
  <div class="drawer-overlay" @click.self="emit('close')">
    <aside class="drawer">
      <header class="d-head">
        <div class="row" style="gap: 10px; align-items: center;">
          <span class="logo">{{ initial(client.company_name || client.client_name) }}</span>
          <div>
            <strong>{{ client.company_name || client.client_name }}</strong>
            <div class="muted" style="font-size: 12px;">Team Members</div>
          </div>
        </div>
        <button class="x" @click="emit('close')">✕</button>
      </header>

      <div class="row" style="justify-content: space-between; align-items: center;">
        <span class="muted">{{ members.length }} member{{ members.length === 1 ? '' : 's' }}</span>
        <button class="btn" @click="openAdd">＋ Add Member</button>
      </div>

      <!-- Add/Edit form -->
      <div v-if="editing" class="mcard editor">
        <div class="etitle">{{ editing === 'new' ? 'ADD TEAM MEMBER' : 'EDIT MEMBER' }}</div>
        <div class="row" style="gap: 12px; align-items: center;">
          <span class="avatar lg"><img v-if="form.avatar_url" :src="form.avatar_url" alt="" /><span v-else>{{ initial(form.full_name) || '?' }}</span></span>
          <label class="btn secondary small">📷 {{ uploading ? 'Uploading…' : (form.avatar_url ? 'Change Photo' : 'Upload Photo') }}<input type="file" accept="image/*" hidden @change="uploadPhoto" /></label>
          <button v-if="form.avatar_url" class="link danger" @click="form.avatar_url = ''">Remove</button>
        </div>
        <div class="grid2">
          <label class="stack"><span>Full Name *</span><input v-model="form.full_name" class="input" placeholder="e.g. Jane Smith" /></label>
          <label class="stack"><span>Email *</span><input v-model="form.email" class="input" type="email" placeholder="jane@company.com" /></label>
          <label class="stack"><span>Role / Title</span><input v-model="form.role" class="input" placeholder="e.g. Marketing Manager" /></label>
          <label class="stack"><span>Access role</span>
            <select v-model="form.client_role_slug" class="input">
              <option value="">No custom role</option>
              <option v-for="r in roles" :key="r.id" :value="r.slug">{{ r.name }}</option>
            </select>
          </label>
        </div>
        <p v-if="error" class="err">{{ error }}</p>
        <div class="row" style="justify-content: flex-end; gap: 10px;">
          <button class="link" @click="close">✕ Cancel</button>
          <button class="btn" :disabled="!form.full_name || saving" @click="save()">✓ Save</button>
        </div>
      </div>

      <!-- Members -->
      <div v-for="m in members" :key="m.id" class="mcard">
        <div class="row" style="gap: 10px; align-items: center;">
          <span class="avatar"><img v-if="m.avatar_url" :src="m.avatar_url" alt="" /><span v-else>{{ initial(m.full_name) }}</span></span>
          <strong>{{ m.full_name }}</strong>
          <span class="tag" :class="{ owner: isOwner(m) }">{{ isOwner(m) ? 'owner' : 'team_member' }}</span>
          <span class="tag ok">{{ m.status || 'active' }}</span>
        </div>
        <div class="muted mrow">✉ {{ m.email || '—' }} <template v-if="m.role">· 🏷 {{ m.role }}</template></div>
        <div class="row actions">
          <button class="chip-btn" @click="viewAs(m)">👁 View as</button>
          <button class="ic" title="Edit" @click="openEdit(m)">✎</button>
          <button class="ic del" title="Delete" @click="remove(m.id)">🗑</button>
        </div>
      </div>

      <p class="muted note">Team members added here can submit requests on behalf of {{ client.company_name || client.client_name }}. Their email will be used for request attribution.</p>
    </aside>
  </div>
</template>

<style scoped>
.drawer-overlay { position: fixed; inset: 0; background: rgba(20,28,40,.4); z-index: 60; display: flex; justify-content: flex-end; }
.drawer { width: min(460px, 96vw); background: var(--bg); height: 100%; overflow: auto; padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.d-head { display: flex; justify-content: space-between; align-items: flex-start; }
.logo { width: 34px; height: 34px; border-radius: 9px; background: #eef2fb; color: var(--primary); display: grid; place-items: center; font-weight: 700; }
.x { border: none; background: none; font-size: 16px; color: var(--muted); cursor: pointer; }
.mcard { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; display: flex; flex-direction: column; gap: 8px; }
.editor { border-color: #c7d2fe; background: #f7f8ff; }
.etitle { font-size: 11px; font-weight: 700; letter-spacing: .05em; color: var(--muted); }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: #eef2f8; color: var(--primary); display: grid; place-items: center; font-size: 12px; font-weight: 700; overflow: hidden; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar.lg { width: 56px; height: 56px; font-size: 18px; }
.tag { font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #eef2fb; color: var(--primary); }
.tag.owner { background: #ede9fe; color: #6d28d9; }
.tag.ok { background: #e6f6f2; color: #0b7a68; }
.mrow { font-size: 13px; }
.actions { gap: 8px; }
.chip-btn { border: 1px solid var(--primary); background: var(--surface); color: var(--primary); border-radius: 999px; padding: 3px 12px; font-size: 13px; cursor: pointer; }
.ic { border: 1px solid var(--border); background: var(--surface); border-radius: 8px; width: 30px; height: 30px; cursor: pointer; }
.ic.del { color: var(--danger); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label.stack span { font-size: 12px; color: var(--muted); }
.link { border: none; background: none; color: var(--primary); cursor: pointer; }
.link.danger { color: var(--danger); }
.small { font-size: 13px; padding: 6px 10px; }
.err { color: var(--danger); margin: 0; }
.note { font-size: 12px; margin-top: 4px; }
</style>
