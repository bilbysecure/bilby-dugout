<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { ProjectsApi, QuotesApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const qc = useQueryClient();
const isManager = computed(() => auth.isManager);
const isOwner = computed(() => auth.role === 'client_owner');

const { data: projData, isLoading } = useQuery({ queryKey: ['projects'], queryFn: () => ProjectsApi.list() });
const projects = computed(() => projData.value ?? []);

const selectedId = ref(null);
const { data: detailData } = useQuery({
  queryKey: ['project', selectedId],
  queryFn: () => ProjectsApi.get(selectedId.value),
  enabled: computed(() => !!selectedId.value),
});
const detail = computed(() => detailData.value ?? null);
const quotes = computed(() => detail.value?.quotes ?? []);

const money = (n, c = 'AUD') => (n == null ? '—' : `${new Intl.NumberFormat('en-AU', { style: 'currency', currency: c }).format(Number(n))}`);
const badge = (s) => ({ draft: 'off', quoted: 'info', sent: 'info', accepted: 'ok', active: 'ok', completed: 'ok', declined: 'danger', expired: 'danger', cancelled: 'danger' }[s] || 'off');
const refresh = () => { qc.invalidateQueries({ queryKey: ['projects'] }); qc.invalidateQueries({ queryKey: ['project', selectedId.value] }); };

// create project (manager)
const showNew = ref(false);
const np = reactive({ title: '', type: 'ad_campaign', client_email: '', price: '' });
const { mutate: createProject, isPending: creating } = useMutation({
  mutationFn: () => ProjectsApi.create({ ...np, price: np.price === '' ? null : Number(np.price) }),
  onSuccess: (p) => { showNew.value = false; Object.assign(np, { title: '', type: 'ad_campaign', client_email: '', price: '' }); refresh(); selectedId.value = p.id; },
});

// create quote (manager)
const showQuote = ref(false);
const nq = reactive({ items: [{ description: '', qty: 1, unit_price: 0 }], deposit_pct: 30, terms: '', valid_until: '' });
const nqSubtotal = computed(() => nq.items.reduce((s, i) => s + Number(i.qty || 0) * Number(i.unit_price || 0), 0));
const { mutate: createQuote } = useMutation({
  mutationFn: () => QuotesApi.create(selectedId.value, { line_items: nq.items, deposit_pct: Number(nq.deposit_pct), terms: nq.terms, valid_until: nq.valid_until || null }),
  onSuccess: () => { showQuote.value = false; nq.items = [{ description: '', qty: 1, unit_price: 0 }]; refresh(); },
});

const { mutate: sendQuote } = useMutation({ mutationFn: (id) => QuotesApi.send(id), onSuccess: refresh });
const { mutate: declineQuote } = useMutation({ mutationFn: (id) => QuotesApi.decline(id), onSuccess: refresh });

// accept with signature (owner)
const signing = ref(null); // quote id being signed
const signature = ref('');
const acceptErr = ref('');
const { mutate: acceptQuote, isPending: accepting } = useMutation({
  mutationFn: (id) => QuotesApi.accept(id, { digital_signature: signature.value }),
  onSuccess: () => { signing.value = null; signature.value = ''; acceptErr.value = ''; refresh(); },
  onError: (e) => { acceptErr.value = e?.response?.data?.error?.message || 'Could not accept'; },
});

// deposit checkout (owner)
const { mutate: payDeposit } = useMutation({
  mutationFn: (id) => QuotesApi.depositCheckout(id),
  onSuccess: (d) => { if (d?.url) window.location.href = d.url; },
});
</script>

<template>
  <section class="stack">
    <header class="row" style="justify-content: space-between; align-items: flex-start;">
      <div>
        <h2>Projects</h2>
        <p class="muted">One-off engagements — quote, deposit, deliver.</p>
      </div>
      <button v-if="isManager" class="btn" @click="showNew = !showNew">＋ New Project</button>
    </header>

    <div v-if="showNew" class="card stack">
      <h3>New project</h3>
      <div class="grid2">
        <label class="stack"><span>Title *</span><input v-model="np.title" class="input" /></label>
        <label class="stack"><span>Client email *</span><input v-model="np.client_email" class="input" placeholder="owner@acme.com" /></label>
        <label class="stack"><span>Type</span><select v-model="np.type" class="input"><option value="ad_campaign">Ad campaign</option><option value="one_off">One-off</option></select></label>
        <label class="stack"><span>Est. price (AUD)</span><input v-model="np.price" class="input" type="number" min="0" /></label>
      </div>
      <div class="row" style="gap: 8px;"><button class="btn" :disabled="creating || !np.title || !np.client_email" @click="createProject()">Create</button><button class="btn secondary" @click="showNew = false">Cancel</button></div>
    </div>

    <div class="split">
      <div class="card list">
        <p v-if="isLoading" class="muted">Loading…</p>
        <button v-for="p in projects" :key="p.id" class="prow" :class="{ sel: selectedId === p.id }" @click="selectedId = p.id">
          <div><strong>{{ p.title }}</strong><div class="muted em">{{ p.client_email }}</div></div>
          <span class="badge" :class="badge(p.status)">{{ p.status }}</span>
        </button>
        <p v-if="!isLoading && !projects.length" class="muted">No projects yet.</p>
      </div>

      <div v-if="detail" class="card detail stack">
        <div class="row" style="justify-content: space-between;">
          <div><h3 style="margin:0;">{{ detail.title }}</h3><span class="muted">{{ detail.type }} · {{ money(detail.price, detail.currency) }}</span></div>
          <span class="badge" :class="badge(detail.status)">{{ detail.status }}</span>
        </div>
        <div class="row" style="gap: 8px;">
          <button v-if="isManager" class="btn secondary small" @click="showQuote = !showQuote">＋ Quote</button>
          <button v-if="isManager && detail.status === 'active'" class="btn secondary small" @click="ProjectsApi.complete(detail.id).then(refresh)">Mark complete</button>
        </div>

        <div v-if="showQuote" class="card sub stack">
          <strong>New quote</strong>
          <div v-for="(it, i) in nq.items" :key="i" class="row" style="gap: 6px;">
            <input v-model="it.description" class="input" placeholder="Line item" style="flex:2;" />
            <input v-model.number="it.qty" class="input" type="number" min="1" style="width:70px;" />
            <input v-model.number="it.unit_price" class="input" type="number" min="0" placeholder="unit" style="width:110px;" />
          </div>
          <button class="link" @click="nq.items.push({ description: '', qty: 1, unit_price: 0 })">+ line</button>
          <div class="row" style="gap: 8px;">
            <label class="stack"><span>Deposit %</span><input v-model.number="nq.deposit_pct" class="input" type="number" min="0" max="100" style="width:90px;" /></label>
            <label class="stack"><span>Valid until</span><input v-model="nq.valid_until" class="input" type="date" /></label>
            <div class="stack"><span>Subtotal</span><strong style="padding-top:8px;">{{ money(nqSubtotal) }}</strong></div>
          </div>
          <label class="stack"><span>Terms</span><textarea v-model="nq.terms" class="input" rows="2" /></label>
          <div><button class="btn small" @click="createQuote()">Create quote</button></div>
        </div>

        <div v-for="q in quotes" :key="q.id" class="card quote">
          <div class="row" style="justify-content: space-between;">
            <strong>Quote #{{ q.id }}</strong><span class="badge" :class="badge(q.status)">{{ q.status }}</span>
          </div>
          <div class="muted" style="font-size:13px;">Subtotal {{ money(q.subtotal, q.currency) }} · Deposit {{ money(q.deposit_amount, q.currency) }} ({{ q.deposit_pct }}%)</div>
          <ul class="items"><li v-for="(li, i) in q.line_items" :key="i">{{ li.description }} — {{ li.qty }} × {{ money(li.unit_price, q.currency) }}</li></ul>
          <p v-if="q.terms" class="muted em">Terms: {{ q.terms }}</p>

          <!-- manager actions -->
          <div v-if="isManager && q.status === 'draft'" class="row" style="gap:8px;"><button class="btn small" @click="sendQuote(q.id)">Send to client</button></div>

          <!-- owner actions -->
          <template v-if="isOwner && q.status === 'sent'">
            <div v-if="signing !== q.id" class="row" style="gap:8px;">
              <button class="btn small" @click="signing = q.id">Accept & sign</button>
              <button class="btn secondary small" @click="declineQuote(q.id)">Decline</button>
            </div>
            <div v-else class="stack">
              <label class="stack"><span>Type your full name to sign</span><input v-model="signature" class="input" placeholder="Your name" /></label>
              <p v-if="acceptErr" class="err">{{ acceptErr }}</p>
              <div class="row" style="gap:8px;"><button class="btn small" :disabled="accepting || !signature" @click="acceptQuote(q.id)">Sign & accept</button><button class="btn secondary small" @click="signing = null">Cancel</button></div>
            </div>
          </template>

          <div v-if="isOwner && q.status === 'accepted'" class="row" style="gap:8px;">
            <button class="btn small" @click="payDeposit(q.id)">Pay deposit {{ money(q.deposit_amount, q.currency) }}</button>
            <span class="muted em">Secure Stripe checkout</span>
          </div>
          <p v-if="q.status === 'accepted' && q.accepted_by" class="muted em">✓ Accepted by {{ q.accepted_by }}</p>
        </div>
        <p v-if="!quotes.length" class="muted">No quotes yet.</p>
      </div>

      <div v-else class="card detail muted" style="display:grid;place-items:center;">Select a project</div>
    </div>
  </section>
</template>

<style scoped>
.split { display: grid; grid-template-columns: 300px 1fr; gap: 16px; align-items: start; }
.list { padding: 8px; display: flex; flex-direction: column; gap: 4px; }
.prow { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 10px; border: none; background: none; border-radius: 8px; cursor: pointer; text-align: left; }
.prow:hover { background: var(--bg); } .prow.sel { background: #eef2fb; }
.detail { min-height: 200px; }
.sub { background: var(--bg); }
.quote { border: 1px solid var(--border); }
.items { margin: 6px 0; padding-left: 18px; font-size: 13px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.em { font-size: 12px; }
.badge.ok { background: #e6f6f2; color: #0b7a68; } .badge.info { background: #eef2fb; color: var(--primary); }
.badge.off { background: #eee; color: var(--muted); } .badge.danger { background: #fdecea; color: var(--danger); }
.btn.small, .link { font-size: 13px; } .link { border: none; background: none; color: var(--primary); cursor: pointer; padding: 0; align-self: flex-start; }
.err { color: var(--danger); font-size: 13px; margin: 0; }
h3 { margin: 0 0 6px; }
</style>
