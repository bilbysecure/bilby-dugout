<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { RequestsApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const id = Number(route.params.id);
const auth = useAuthStore();
const qc = useQueryClient();

const { data: request, isLoading } = useQuery({ queryKey: ['request', id], queryFn: () => RequestsApi.get(id) });
const { data: comments } = useQuery({ queryKey: ['request', id, 'comments'], queryFn: () => RequestsApi.comments(id) });
const { data: approvals } = useQuery({ queryKey: ['request', id, 'approvals'], queryFn: () => RequestsApi.approvals(id) });

const fmt = (s) => (s ? String(s).replaceAll('_', ' ') : '—');
const canApprove = computed(() => auth.role === 'client_owner' && request.value?.status === 'review');

// ── Add comment ──────────────────────────────────────────────
const newComment = ref('');
const isInternal = ref(false);
const { mutate: postComment, isPending: posting } = useMutation({
  mutationFn: () => RequestsApi.addComment(id, { message: newComment.value, is_internal: isInternal.value }),
  onSuccess: () => { newComment.value = ''; qc.invalidateQueries({ queryKey: ['request', id, 'comments'] }); },
});

// ── Approve / request revision ───────────────────────────────
const signature = ref('');
const { mutate: submitDecision, isPending: deciding } = useMutation({
  mutationFn: (decision) =>
    RequestsApi.approve(id, { decision, digital_signature: signature.value }),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['request', id] });
    qc.invalidateQueries({ queryKey: ['request', id, 'approvals'] });
  },
});
</script>

<template>
  <section v-if="isLoading" class="muted">Loading…</section>
  <section v-else-if="request" class="detail stack">
    <header class="row" style="justify-content: space-between; align-items: flex-start;">
      <div>
        <h2>{{ request.title }}</h2>
        <div class="row" style="gap: 8px;">
          <span class="badge">{{ fmt(request.status) }}</span>
          <span class="muted">{{ fmt(request.type) }} · {{ fmt(request.priority) }}</span>
        </div>
      </div>
      <router-link :to="{ name: 'requests' }" class="muted">← Back</router-link>
    </header>

    <div class="card">
      <p>{{ request.description || 'No description.' }}</p>
      <p class="muted" v-if="request.due_date">Due: {{ request.due_date }}</p>
      <p class="muted" v-if="request.assigned_to?.length">Assigned: {{ request.assigned_to.join(', ') }}</p>
    </div>

    <!-- Approval (client owner) -->
    <div v-if="canApprove" class="card stack">
      <strong>Review &amp; sign off</strong>
      <input v-model="signature" class="input" placeholder="Type your full name as signature" />
      <div class="row">
        <button class="btn" :disabled="!signature || deciding" @click="submitDecision('approved')">Approve</button>
        <button class="btn secondary" :disabled="!signature || deciding" @click="submitDecision('revision_requested')">Request revision</button>
      </div>
    </div>

    <div v-if="approvals?.length" class="card">
      <strong>Approvals</strong>
      <ul>
        <li v-for="a in approvals" :key="a.id" class="muted">
          {{ fmt(a.decision) }} — {{ a.signed_by_name }} ({{ a.digital_signature }})
        </li>
      </ul>
    </div>

    <!-- Comments -->
    <div class="card stack">
      <strong>Comments</strong>
      <p v-if="!comments?.length" class="muted">No comments yet.</p>
      <div v-for="c in comments" :key="c.id" class="comment">
        <div class="row" style="gap: 8px;">
          <strong>{{ c.author_name }}</strong>
          <span v-if="c.is_internal" class="badge">internal</span>
        </div>
        <p>{{ c.message }}</p>
      </div>

      <form class="stack" @submit.prevent="postComment()">
        <textarea v-model="newComment" class="input" rows="2" placeholder="Add a comment…" required />
        <div class="row" style="justify-content: space-between;">
          <label v-if="auth.isAgency" class="row" style="gap: 6px;">
            <input type="checkbox" v-model="isInternal" /> <span class="muted">Internal note</span>
          </label>
          <button class="btn" :disabled="!newComment || posting">Post</button>
        </div>
      </form>
    </div>
  </section>
</template>

<style scoped>
.detail { max-width: 760px; }
.comment { border-bottom: 1px solid var(--border); padding-bottom: 8px; }
.comment p { margin: 4px 0 0; }
</style>
