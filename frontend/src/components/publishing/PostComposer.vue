<script setup>
import { reactive, ref, computed, watch, onMounted } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { PublishingApi, ChannelsApi, ProductsApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';
import { platformMeta, statusMeta } from '@/constants/platforms';

const props = defineProps({
  postId: { type: Number, default: null },
  initialDate: { type: String, default: '' },
});
const emit = defineEmits(['close', 'saved']);

const auth = useAuthStore();
const qc = useQueryClient();

const channels = ref([]);
const products = ref([]);
const post = ref(null);
const bestTimes = ref([]);
const busy = ref(false);
const error = ref('');
const tagInput = ref('');

const form = reactive({
  title: '', caption: '', link: '', first_comment: '',
  tags: [], media: [], channel_ids: [], product_tags: [], scheduled_at: '',
});

const status = computed(() => post.value?.status ?? 'draft');
const isNew = computed(() => !props.postId);
const canApprove = computed(() => auth.isManager || auth.role === 'client_owner');
const channelsByPlatform = computed(() => {
  const g = {};
  for (const c of channels.value) (g[c.platform] ||= []).push(c);
  return g;
});
const firstPlatform = computed(() => {
  const c = channels.value.find((x) => form.channel_ids.includes(x.id));
  return c?.platform || 'instagram';
});

onMounted(async () => {
  [channels.value, products.value] = await Promise.all([ChannelsApi.list(), ProductsApi.list()]);
  if (props.postId) {
    const p = await PublishingApi.post(props.postId);
    post.value = p;
    Object.assign(form, {
      title: p.title || '', caption: p.caption || '', link: p.link || '', first_comment: p.first_comment || '',
      tags: p.tags || [], media: p.media?.map((m) => (typeof m === 'string' ? m : m.url)) || [],
      channel_ids: (p.targets || []).map((t) => t.social_channel_id),
      product_tags: p.product_tags || [], scheduled_at: p.scheduled_at ? p.scheduled_at.slice(0, 16) : '',
    });
  } else if (props.initialDate) {
    form.scheduled_at = `${props.initialDate}T10:00`;
  }
});

function toggleChannel(id) {
  const i = form.channel_ids.indexOf(id);
  if (i === -1) form.channel_ids.push(id); else form.channel_ids.splice(i, 1);
}
function addTag() {
  const t = tagInput.value.trim().replace(/^#/, '');
  if (t && !form.tags.includes(t)) form.tags.push(t);
  tagInput.value = '';
}
function toggleProduct(id) {
  const i = form.product_tags.findIndex((p) => p.product_id === id);
  if (i === -1) form.product_tags.push({ product_id: id });
  else form.product_tags.splice(i, 1);
}
const hasProduct = (id) => form.product_tags.some((p) => p.product_id === id);
const igSelected = computed(() => channels.value.some((c) => c.platform === 'instagram' && form.channel_ids.includes(c.id)));

async function suggestTimes() {
  bestTimes.value = await PublishingApi.bestTimes(firstPlatform.value, 'UTC');
}

function payload() {
  return {
    title: form.title, caption: form.caption, link: form.link, first_comment: form.first_comment,
    tags: form.tags, media: form.media.filter(Boolean).map((url) => ({ url, type: 'image' })),
    channel_ids: form.channel_ids, product_tags: form.product_tags,
    scheduled_at: form.scheduled_at ? form.scheduled_at.replace('T', ' ') + ':00' : null,
  };
}

async function run(fn) {
  busy.value = true; error.value = '';
  try {
    await fn();
    qc.invalidateQueries({ queryKey: ['posts'] });
    emit('saved');
  } catch (e) {
    error.value = e?.response?.data?.error?.message || 'Something went wrong.';
  } finally {
    busy.value = false;
  }
}

async function ensureSaved() {
  if (isNew.value) {
    post.value = await PublishingApi.createPost(payload());
  } else {
    post.value = await PublishingApi.updatePost(props.postId, payload());
  }
  return post.value.id;
}

const saveDraft = () => run(async () => { await ensureSaved(); });
const submit = () => run(async () => { const id = await ensureSaved(); await PublishingApi.submitPost(id); });
const schedule = () => run(async () => { const id = await ensureSaved(); await PublishingApi.schedulePost(id, payload().scheduled_at); });
const publishNow = () => run(async () => { const id = await ensureSaved(); await PublishingApi.publishPost(id); });
const cancelPost = () => run(async () => { await PublishingApi.cancelPost(props.postId); });
const decide = (decision) => run(async () => { await PublishingApi.decidePost(props.postId, { decision }); });
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="composer card">
      <header class="row" style="justify-content: space-between;">
        <div class="row" style="gap: 10px;">
          <h3 style="margin: 0;">{{ isNew ? 'New post' : 'Edit post' }}</h3>
          <span class="badge" :style="{ background: statusMeta(status).color + '22', color: statusMeta(status).color }">
            {{ statusMeta(status).label }}
          </span>
        </div>
        <button class="icon-btn" @click="emit('close')">✕</button>
      </header>

      <div class="cols">
        <!-- Left: content -->
        <div class="stack">
          <input v-model="form.title" class="input" placeholder="Title (internal)" />
          <textarea v-model="form.caption" class="input" rows="5" placeholder="Write your caption…" />

          <label class="lbl">Media URLs</label>
          <div v-for="(m, i) in form.media" :key="i" class="row">
            <input v-model="form.media[i]" class="input" placeholder="https://…/image.jpg" />
            <button class="icon-btn" @click="form.media.splice(i, 1)">✕</button>
          </div>
          <button class="btn secondary small" @click="form.media.push('')">+ Add media</button>

          <label class="lbl">First comment (optional)</label>
          <input v-model="form.first_comment" class="input" placeholder="e.g. hashtags in first comment" />
          <label class="lbl">Link (optional)</label>
          <input v-model="form.link" class="input" placeholder="https://…" />

          <label class="lbl">Tags <span class="muted">— rules may add more automatically</span></label>
          <div class="chips">
            <span v-for="t in form.tags" :key="t" class="chip on" @click="form.tags.splice(form.tags.indexOf(t), 1)">#{{ t }} ✕</span>
          </div>
          <input v-model="tagInput" class="input" placeholder="Add a tag, press Enter" @keyup.enter="addTag" />
        </div>

        <!-- Right: distribution -->
        <div class="stack">
          <label class="lbl">Channels &amp; brands</label>
          <div v-for="(list, plat) in channelsByPlatform" :key="plat" class="chan-group">
            <span class="plat" :style="{ background: platformMeta(plat).color }">{{ platformMeta(plat).letter }}</span>
            <div class="chips">
              <span
                v-for="c in list" :key="c.id"
                class="chip" :class="{ on: form.channel_ids.includes(c.id) }"
                @click="toggleChannel(c.id)"
              >{{ c.account_name }}</span>
            </div>
          </div>
          <p v-if="!channels.length" class="muted">No channels connected — add them in Publishing → Settings.</p>

          <label class="lbl">Schedule</label>
          <input v-model="form.scheduled_at" class="input" type="datetime-local" />
          <div class="row" style="flex-wrap: wrap; gap: 6px;">
            <button class="btn secondary small" @click="suggestTimes">✨ Suggest best time</button>
            <span
              v-for="s in bestTimes" :key="s.datetime"
              class="chip" @click="form.scheduled_at = s.datetime"
              :title="`score ${s.score}`"
            >{{ s.label }}</span>
          </div>

          <template v-if="igSelected && products.length">
            <label class="lbl">Instagram product tags</label>
            <div class="chips">
              <span
                v-for="pr in products" :key="pr.id"
                class="chip" :class="{ on: hasProduct(pr.id) }"
                @click="toggleProduct(pr.id)"
              >🛍 {{ pr.name }}</span>
            </div>
          </template>
        </div>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <footer class="row actions">
        <button class="btn secondary" :disabled="busy" @click="saveDraft">Save draft</button>
        <button class="btn secondary" :disabled="busy" @click="submit">Submit for approval</button>
        <span class="spacer" />
        <template v-if="status === 'pending_approval' && canApprove">
          <button class="btn secondary" :disabled="busy" @click="decide('rejected')">Reject</button>
          <button class="btn" :disabled="busy" @click="decide('approved')">Approve</button>
        </template>
        <button class="btn" :disabled="busy || !form.scheduled_at" @click="schedule">Schedule</button>
        <button class="btn" :disabled="busy" @click="publishNow">Publish now</button>
        <button v-if="!isNew && status !== 'cancelled'" class="btn danger" :disabled="busy" @click="cancelPost">Cancel post</button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(20, 28, 40, 0.45); display: grid; place-items: center; z-index: 50; padding: 20px; }
.composer { width: min(880px, 96vw); max-height: 92vh; overflow: auto; display: flex; flex-direction: column; gap: 14px; }
.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.lbl { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { border: 1px solid var(--border); background: var(--surface); padding: 5px 10px; border-radius: 999px; font-size: 13px; cursor: pointer; }
.chip.on { border-color: var(--primary); background: #eef2f8; color: var(--primary); font-weight: 600; }
.chan-group { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.plat { width: 26px; height: 26px; border-radius: 7px; color: #fff; display: grid; place-items: center; font-size: 11px; font-weight: 700; flex: none; }
.actions { flex-wrap: wrap; gap: 8px; border-top: 1px solid var(--border); padding-top: 12px; }
.spacer { flex: 1; }
.small { padding: 5px 10px; font-size: 13px; }
.icon-btn { border: none; background: transparent; font-size: 16px; color: var(--muted); }
.btn.danger { border-color: var(--danger); background: var(--surface); color: var(--danger); }
.error { color: var(--danger); margin: 0; }
</style>
