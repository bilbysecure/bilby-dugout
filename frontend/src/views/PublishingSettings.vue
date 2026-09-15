<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useQueryClient } from '@tanstack/vue-query';
import { useChannelsQuery, useTagRulesQuery, useRssQuery, useProductsQuery } from '@/composables/queries';
import { ChannelsApi, TagRulesApi, RssApi, ProductsApi } from '@/api/endpoints';
import { PLATFORM_KEYS, platformMeta } from '@/constants/platforms';

const router = useRouter();
const qc = useQueryClient();
const tab = ref('channels');
const tabs = [
  { key: 'channels', label: 'Channels' },
  { key: 'rules', label: 'Tag automation' },
  { key: 'rss', label: 'RSS sources' },
  { key: 'products', label: 'Products' },
];
const busy = ref(false);
const msg = ref('');

const { data: channels } = useChannelsQuery();
const { data: rules } = useTagRulesQuery();
const { data: sources } = useRssQuery();
const { data: products } = useProductsQuery();
const list = (r) => r.value ?? [];

async function act(fn, key) {
  busy.value = true; msg.value = '';
  try { const r = await fn(); qc.invalidateQueries({ queryKey: [key] }); return r; }
  catch (e) { msg.value = e?.response?.data?.error?.message || 'Error'; }
  finally { busy.value = false; }
}

// Channel form
const chan = reactive({ platform: 'instagram', account_name: '', timezone: 'UTC' });
const addChannel = () => act(async () => { await ChannelsApi.create({ ...chan }); chan.account_name = ''; }, 'channels');
const delChannel = (id) => act(() => ChannelsApi.remove(id), 'channels');

// Rule form
const rule = reactive({ name: '', match_type: 'keyword', pattern: '', add_tags: '', priority: 0 });
const addRule = () => act(async () => {
  await TagRulesApi.create({ name: rule.name, match_type: rule.match_type, pattern: rule.pattern, priority: Number(rule.priority), add_tags: rule.add_tags.split(',').map((s) => s.trim()).filter(Boolean) });
  rule.name = ''; rule.pattern = ''; rule.add_tags = '';
}, 'tag-rules');
const delRule = (id) => act(() => TagRulesApi.remove(id), 'tag-rules');

// RSS form
const src = reactive({ name: '', feed_url: '', default_status: 'draft', auto_schedule: false });
const addSource = () => act(async () => { await RssApi.create({ ...src }); src.name = ''; src.feed_url = ''; }, 'rss-sources');
const delSource = (id) => act(() => RssApi.remove(id), 'rss-sources');
const ingest = async (id) => { const r = await act(() => RssApi.ingest(id), 'posts'); if (r) msg.value = `Ingested ${r.created} new, skipped ${r.skipped}.`; };

// Product form
const prod = reactive({ name: '', price: '' });
const addProduct = () => act(async () => { await ProductsApi.create({ name: prod.name, price: prod.price ? Number(prod.price) : null }); prod.name = ''; prod.price = ''; }, 'products');
</script>

<template>
  <section class="stack">
    <header class="row" style="justify-content: space-between;">
      <div>
        <h2>Publishing settings</h2>
        <p class="muted">Channels, tag automation, RSS feeds and product catalog.</p>
      </div>
      <button class="btn secondary" @click="router.push({ name: 'publishing' })">← Back to calendar</button>
    </header>

    <div class="tabs">
      <button v-for="t in tabs" :key="t.key" class="tab" :class="{ active: tab === t.key }" @click="tab = t.key">{{ t.label }}</button>
    </div>
    <p v-if="msg" class="muted">{{ msg }}</p>

    <!-- Channels -->
    <div v-if="tab === 'channels'" class="card stack">
      <div v-for="c in list(channels)" :key="c.id" class="row line">
        <span class="plat" :style="{ background: platformMeta(c.platform).color }">{{ platformMeta(c.platform).letter }}</span>
        <strong>{{ c.account_name }}</strong>
        <span class="muted">{{ platformMeta(c.platform).label }} · {{ c.timezone }}</span>
        <button class="icon-btn" style="margin-left: auto;" @click="delChannel(c.id)">✕</button>
      </div>
      <p v-if="!list(channels).length" class="muted">No channels yet.</p>
      <div class="row add">
        <select v-model="chan.platform" class="input">
          <option v-for="p in PLATFORM_KEYS" :key="p" :value="p">{{ platformMeta(p).label }}</option>
        </select>
        <input v-model="chan.account_name" class="input" placeholder="Account name / handle" />
        <input v-model="chan.timezone" class="input" placeholder="Timezone" style="max-width: 160px;" />
        <button class="btn" :disabled="busy || !chan.account_name" @click="addChannel">Add</button>
      </div>
    </div>

    <!-- Tag rules -->
    <div v-else-if="tab === 'rules'" class="card stack">
      <div v-for="r in list(rules)" :key="r.id" class="row line">
        <span class="badge">{{ r.match_type }}<template v-if="r.pattern">: {{ r.pattern }}</template></span>
        <strong>{{ r.name }}</strong>
        <span class="muted">→ {{ (r.add_tags || []).map((t) => '#' + t).join(' ') }}</span>
        <button class="icon-btn" style="margin-left: auto;" @click="delRule(r.id)">✕</button>
      </div>
      <p v-if="!list(rules).length" class="muted">No rules yet.</p>
      <div class="grid-form">
        <input v-model="rule.name" class="input" placeholder="Rule name" />
        <select v-model="rule.match_type" class="input">
          <option value="keyword">keyword</option><option value="hashtag">hashtag</option>
          <option value="platform">platform</option><option value="all">all</option>
        </select>
        <input v-model="rule.pattern" class="input" placeholder="Pattern (e.g. launch)" />
        <input v-model="rule.add_tags" class="input" placeholder="Add tags (comma sep)" />
        <input v-model="rule.priority" class="input" type="number" placeholder="Priority" style="max-width: 110px;" />
        <button class="btn" :disabled="busy || !rule.name" @click="addRule">Add rule</button>
      </div>
    </div>

    <!-- RSS -->
    <div v-else-if="tab === 'rss'" class="card stack">
      <div v-for="s in list(sources)" :key="s.id" class="row line">
        <strong>{{ s.name }}</strong>
        <span class="muted">{{ s.feed_url }}</span>
        <span class="badge">{{ s.default_status }}</span>
        <span v-if="s.auto_schedule" class="badge">auto-schedule</span>
        <span class="muted">{{ s.last_status || 'not fetched' }}</span>
        <span class="row" style="margin-left: auto; gap: 6px;">
          <button class="btn secondary small" :disabled="busy" @click="ingest(s.id)">Ingest now</button>
          <button class="icon-btn" @click="delSource(s.id)">✕</button>
        </span>
      </div>
      <p v-if="!list(sources).length" class="muted">No feeds yet.</p>
      <div class="grid-form">
        <input v-model="src.name" class="input" placeholder="Feed name" />
        <input v-model="src.feed_url" class="input" placeholder="https://…/feed" />
        <select v-model="src.default_status" class="input">
          <option value="draft">create as draft</option>
          <option value="pending_approval">create as pending approval</option>
        </select>
        <label class="row" style="gap: 6px;"><input type="checkbox" v-model="src.auto_schedule" /> <span class="muted">auto-schedule</span></label>
        <button class="btn" :disabled="busy || !src.feed_url" @click="addSource">Add feed</button>
      </div>
    </div>

    <!-- Products -->
    <div v-else class="card stack">
      <div v-for="pr in list(products)" :key="pr.id" class="row line">
        <strong>🛍 {{ pr.name }}</strong>
        <span class="muted">{{ pr.price ? pr.currency + ' ' + pr.price : '—' }}</span>
      </div>
      <p v-if="!list(products).length" class="muted">No products yet.</p>
      <div class="row add">
        <input v-model="prod.name" class="input" placeholder="Product name" />
        <input v-model="prod.price" class="input" type="number" placeholder="Price" style="max-width: 140px;" />
        <button class="btn" :disabled="busy || !prod.name" @click="addProduct">Add</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.tab { border: 1px solid var(--border); background: var(--surface); padding: 7px 14px; border-radius: var(--radius); }
.tab.active { border-color: var(--primary); background: #eef2f8; color: var(--primary); font-weight: 600; }
.line { padding: 8px 0; border-bottom: 1px solid var(--border); gap: 10px; }
.line:last-of-type { border-bottom: none; }
.plat { width: 26px; height: 26px; border-radius: 7px; color: #fff; display: grid; place-items: center; font-size: 11px; font-weight: 700; }
.add { gap: 8px; flex-wrap: wrap; }
.grid-form { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.grid-form .input { flex: 1; min-width: 140px; }
.icon-btn { border: none; background: transparent; font-size: 15px; color: var(--muted); cursor: pointer; }
.small { padding: 5px 10px; font-size: 13px; }
</style>
