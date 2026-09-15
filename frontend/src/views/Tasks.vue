<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { TasksApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const qc = useQueryClient();
const isManager = computed(() => auth.isManager);
const isAgency = computed(() => auth.isAgency);
const isOwner = computed(() => auth.role === 'client_owner');

const { data: listData, isLoading } = useQuery({ queryKey: ['tasks'], queryFn: () => TasksApi.list() });
const tasks = computed(() => listData.value ?? []);

const selectedId = ref(null);
const { data: detailData } = useQuery({
  queryKey: ['task', selectedId],
  queryFn: () => TasksApi.get(selectedId.value),
  enabled: computed(() => !!selectedId.value),
});
const task = computed(() => detailData.value ?? null);
const approvers = computed(() => task.value?.approvers ?? []);
const history = computed(() => task.value?.approvals ?? []);

const badge = (s) => ({
  draft: 'off', pending_internal_approval: 'info', changes_requested: 'danger',
  pending_client_approval: 'info', internal_approved: 'ok', client_approved: 'ok',
  in_progress: 'ok', done: 'ok', cancelled: 'off',
}[s] || 'off');
const label = (s) => (s || '').replace(/_/g, ' ');
const refresh = () => { qc.invalidateQueries({ queryKey: ['tasks'] }); qc.invalidateQueries({ queryKey: ['task', selectedId.value] }); };
const act = (fn) => fn().then((t) => { selectedId.value = t.id; refresh(); }).catch((e) => alert(e?.response?.data?.error?.message || 'Action failed'));

// create (manager)
const showNew = ref(false);
const nt = reactive({ title: '', client_email: '', assigned_to: '', requires_client_approval: true, approversText: '' });
const createTask = () => {
  const approvers = nt.approversText.split(',').map((s) => s.trim()).filter(Boolean).map((email) => ({ approver_id: email, is_required: true }));
  TasksApi.create({ title: nt.title, client_email: nt.client_email, assigned_to: nt.assigned_to, requires_client_approval: nt.requires_client_approval, approvers })
    .then((t) => { showNew.value = false; Object.assign(nt, { title: '', client_email: '', assigned_to: '', requires_client_approval: true, approversText: '' }); refresh(); selectedId.value = t.id; })
    .catch((e) => alert(e?.response?.data?.error?.message || 'Could not create'));
};

// decision form
const dec = reactive({ decision: 'approve', signature: '', comment: '' });
const submitInternal = () => act(() => TasksApi.internalDecision(task.value.id, { ...dec })).then(() => { dec.signature = ''; dec.comment = ''; });
const submitClient = () => act(() => TasksApi.clientDecision(task.value.id, { ...dec })).then(() => { dec.signature = ''; dec.comment = ''; });
</script>

<template>
  <section class="stack">
    <header class="row" style="justify-content: space-between; align-items: flex-start;">
      <div><h2>Tasks</h2><p class="muted">Internal review, then client sign-off.</p></div>
      <button v-if="isManager" class="btn" @click="showNew = !showNew">＋ New Task</button>
    </header>

    <div v-if="showNew" class="card stack">
      <h3>New task</h3>
      <div class="grid2">
        <label class="stack"><span>Title *</span><input v-model="nt.title" class="input" /></label>
        <label class="stack"><span>Client email *</span><input v-model="nt.client_email" class="input" placeholder="owner@acme.com" /></label>
        <label class="stack"><span>Assigned to</span><input v-model="nt.assigned_to" class="input" placeholder="designer@bilbypixel.com" /></label>
        <label class="stack"><span>Required approvers (comma-separated) *</span><input v-model="nt.approversText" class="input" placeholder="a@bilbypixel.com, b@bilbypixel.com" /></label>
      </div>
      <label class="row" style="gap: 8px;"><input type="checkbox" v-model="nt.requires_client_approval" /> Requires client approval</label>
      <div class="row" style="gap: 8px;"><button class="btn" :disabled="!nt.title || !nt.client_email || !nt.approversText" @click="createTask">Create</button><button class="btn secondary" @click="showNew = false">Cancel</button></div>
    </div>

    <div class="split">
      <div class="card list">
        <p v-if="isLoading" class="muted">Loading…</p>
        <button v-for="t in tasks" :key="t.id" class="trow" :class="{ sel: selectedId === t.id }" @click="selectedId = t.id">
          <div><strong>{{ t.title }}</strong><div class="muted em">{{ t.client_email }}</div></div>
          <span class="badge" :class="badge(t.status)">{{ label(t.status) }}</span>
        </button>
        <p v-if="!isLoading && !tasks.length" class="muted">No tasks.</p>
      </div>

      <div v-if="task" class="card detail stack">
        <div class="row" style="justify-content: space-between;">
          <div><h3 style="margin:0;">{{ task.title }}</h3><span class="muted">{{ task.client_email }} · round {{ task.current_round }}</span></div>
          <span class="badge" :class="badge(task.status)">{{ label(task.status) }}</span>
        </div>
        <div class="muted em">Approvers: <span v-for="a in approvers" :key="a.id">{{ a.approver_id }}{{ a.is_required ? '' : ' (optional)' }}<span v-if="a !== approvers[approvers.length-1]">, </span></span></div>
        <div class="muted em">Client approval: {{ task.requires_client_approval ? 'required' : 'internal only' }}</div>

        <!-- lifecycle actions (manager / assignee) -->
        <div class="row" style="gap: 8px; flex-wrap: wrap;">
          <button v-if="isAgency && ['draft','changes_requested'].includes(task.status)" class="btn small" @click="act(() => TasksApi.submit(task.id))">Submit for approval</button>
          <button v-if="isManager && ['internal_approved','client_approved'].includes(task.status)" class="btn small" @click="act(() => TasksApi.start(task.id))">Start work</button>
          <button v-if="isAgency && task.status === 'in_progress'" class="btn small" @click="act(() => TasksApi.complete(task.id))">Mark done</button>
          <button v-if="isManager && !['done','cancelled'].includes(task.status)" class="btn secondary small" @click="act(() => TasksApi.cancel(task.id))">Cancel</button>
        </div>

        <!-- internal decision (agency approvers) -->
        <div v-if="isAgency && task.status === 'pending_internal_approval'" class="card sub stack">
          <strong>Internal decision</strong>
          <select v-model="dec.decision" class="input"><option value="approve">Approve</option><option value="request_changes">Request changes</option></select>
          <input v-model="dec.comment" class="input" placeholder="Comment" />
          <input v-model="dec.signature" class="input" placeholder="Sign (your name)" />
          <button class="btn small" :disabled="!dec.signature" @click="submitInternal">Submit decision</button>
        </div>

        <!-- client decision (client owner) -->
        <div v-if="isOwner && task.status === 'pending_client_approval'" class="card sub stack">
          <strong>Client decision</strong>
          <select v-model="dec.decision" class="input"><option value="approve">Approve</option><option value="request_changes">Request changes</option></select>
          <input v-model="dec.comment" class="input" placeholder="Comment" />
          <input v-model="dec.signature" class="input" placeholder="Sign (your name)" />
          <button class="btn small" :disabled="!dec.signature" @click="submitClient">Submit decision</button>
        </div>

        <!-- approval history -->
        <div>
          <strong>Approval history</strong>
          <table class="tbl">
            <thead><tr><th>Round</th><th>Approver</th><th>Type</th><th>Decision</th><th>Comment</th></tr></thead>
            <tbody>
              <tr v-for="h in history" :key="h.id">
                <td>{{ h.round }}</td><td>{{ h.approver_id }}</td><td class="muted">{{ label(h.approver_type) }}</td>
                <td><span class="badge" :class="h.decision === 'approve' ? 'ok' : 'danger'">{{ label(h.decision) }}</span></td>
                <td class="muted">{{ h.comment }}</td>
              </tr>
              <tr v-if="!history.length"><td colspan="5" class="muted">No decisions yet.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-else class="card detail muted" style="display:grid;place-items:center;">Select a task</div>
    </div>
  </section>
</template>

<style scoped>
.split { display: grid; grid-template-columns: 300px 1fr; gap: 16px; align-items: start; }
.list { padding: 8px; display: flex; flex-direction: column; gap: 4px; }
.trow { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px; border: none; background: none; border-radius: 8px; cursor: pointer; text-align: left; }
.trow:hover { background: var(--bg); } .trow.sel { background: #eef2fb; }
.sub { background: var(--bg); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.em { font-size: 12px; }
.tbl { width: 100%; border-collapse: collapse; margin-top: 6px; }
th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
th { font-size: 11px; color: var(--muted); text-transform: uppercase; }
.badge.ok { background: #e6f6f2; color: #0b7a68; } .badge.info { background: #eef2fb; color: var(--primary); }
.badge.off { background: #eee; color: var(--muted); } .badge.danger { background: #fdecea; color: var(--danger); }
.btn.small { font-size: 13px; padding: 5px 12px; }
h3 { margin: 0 0 6px; }
</style>
