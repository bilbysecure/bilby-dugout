<script setup>
import { ref, reactive, computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { BrandApi, UploadApi } from '@/api/endpoints';

const props = defineProps({
  kitId: { type: Number, required: true },
  category: { type: String, required: true },  // logo | photo | icon | graphic
  label: { type: String, required: true },
  canWrite: { type: Boolean, default: false },
});

const qc = useQueryClient();
const key = computed(() => ['brand-assets', props.kitId]);
const { data, isLoading } = useQuery({ queryKey: key.value, queryFn: () => BrandApi.assets(props.kitId) });
const assets = computed(() => (data.value ?? []).filter((a) => (a.category || 'graphic') === props.category));

const form = reactive({ name: '', file_url: '' });
const uploading = ref(false);
const error = ref('');
const reviewFor = ref(null);   // assetId whose review panel is open
const reviews = reactive({});  // assetId -> review

const invalidate = () => qc.invalidateQueries({ queryKey: key.value });

async function upload(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  uploading.value = true; error.value = '';
  try { form.file_url = await UploadApi.image(file); if (!form.name) form.name = file.name; }
  catch (err) { error.value = err?.response?.data?.error?.message || 'Upload failed'; }
  finally { uploading.value = false; }
}

const { mutate: add, isPending: adding } = useMutation({
  mutationFn: () => BrandApi.addAsset(props.kitId, { name: form.name, category: props.category, file_url: form.file_url }),
  onSuccess: () => { form.name = ''; form.file_url = ''; invalidate(); },
  onError: (e) => { error.value = e?.response?.data?.error?.message || 'Could not add.'; },
});
const { mutate: remove } = useMutation({ mutationFn: (id) => BrandApi.deleteAsset(id), onSuccess: invalidate });

const reviewing = ref(null);
async function runReview(asset) {
  reviewing.value = asset.id;
  try {
    reviews[asset.id] = await BrandApi.reviewAsset(asset.id);
    reviewFor.value = asset.id;
  } finally {
    reviewing.value = null;
  }
}
function reviewOf(a) { return reviews[a.id] || a.review || null; }
const isImg = (u) => u && /\.(png|jpe?g|gif|webp|svg)$/i.test(u);
</script>

<template>
  <div class="stack">
    <div v-if="canWrite" class="add card">
      <input v-model="form.name" class="input" :placeholder="`${label} name`" style="max-width: 240px;" />
      <label class="btn secondary small">
        {{ uploading ? 'Uploading…' : (form.file_url ? 'Change file' : 'Upload file') }}
        <input type="file" accept="image/*" hidden @change="upload" />
      </label>
      <img v-if="isImg(form.file_url)" :src="form.file_url" class="preview" alt="" />
      <button class="btn" :disabled="!form.name || adding" @click="add()">Add {{ label.toLowerCase() }}</button>
    </div>
    <p v-if="error" class="err">{{ error }}</p>

    <p v-if="isLoading" class="muted">Loading…</p>
    <p v-else-if="!assets.length" class="muted">No {{ label.toLowerCase() }}s yet.</p>
    <div v-else class="grid">
      <div v-for="a in assets" :key="a.id" class="asset card">
        <div class="thumb">
          <img v-if="isImg(a.file_url)" :src="a.file_url" alt="" />
          <span v-else>📄</span>
        </div>
        <div class="a-body">
          <strong>{{ a.name }}</strong>
          <span v-if="reviewOf(a)" class="badge" :class="reviewOf(a).status === 'consistent' ? 'ok' : 'warn'">
            {{ reviewOf(a).status === 'consistent' ? 'On-brand' : 'Needs attention' }} · {{ reviewOf(a).score }}
          </span>
          <div class="a-actions">
            <button class="link" :disabled="reviewing === a.id" @click="runReview(a)">
              {{ reviewing === a.id ? 'Reviewing…' : '✨ AI review' }}
            </button>
            <button v-if="canWrite" class="link danger" @click="remove(a.id)">Delete</button>
          </div>
        </div>

        <!-- Review panel -->
        <div v-if="reviewFor === a.id && reviewOf(a)" class="review">
          <p>{{ reviewOf(a).summary }}</p>
          <div v-if="reviewOf(a).matches?.length"><strong class="good">✓ Matches</strong>
            <ul><li v-for="(m, i) in reviewOf(a).matches" :key="i">{{ m }}</li></ul></div>
          <div v-if="reviewOf(a).issues?.length"><strong class="bad">! Issues</strong>
            <ul><li v-for="(m, i) in reviewOf(a).issues" :key="i">{{ m }}</li></ul></div>
          <div v-if="reviewOf(a).recommendations?.length"><strong>Recommendations</strong>
            <ul><li v-for="(m, i) in reviewOf(a).recommendations" :key="i">{{ m }}</li></ul></div>
          <button class="link" @click="reviewFor = null">Hide</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.add { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 12px; }
.preview { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.asset { display: flex; flex-direction: column; gap: 8px; padding: 12px; }
.thumb { height: 110px; border-radius: 8px; background: var(--bg); display: grid; place-items: center; overflow: hidden; font-size: 28px; }
.thumb img { width: 100%; height: 100%; object-fit: contain; }
.a-body { display: flex; flex-direction: column; gap: 5px; }
.a-actions { display: flex; gap: 10px; }
.badge.ok { background: #e6f6f2; color: #0b7a68; }
.badge.warn { background: #fff4e0; color: #a15c00; }
.link { border: none; background: none; color: var(--primary); cursor: pointer; padding: 0; font-size: 13px; }
.link.danger { color: var(--danger); }
.review { border-top: 1px solid var(--border); padding-top: 8px; font-size: 13px; }
.review ul { margin: 3px 0 8px; padding-left: 18px; }
.review .good { color: #0b7a68; } .review .bad { color: var(--danger); }
.small { font-size: 13px; padding: 6px 10px; }
.err { color: var(--danger); margin: 0; }
</style>
