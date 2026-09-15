<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { usePostsQuery, useChannelsQuery } from '@/composables/queries';
import { platformMeta, statusMeta } from '@/constants/platforms';
import PostComposer from '@/components/publishing/PostComposer.vue';

const router = useRouter();
const { data: postsData, isLoading, refetch } = usePostsQuery();
const { data: channelsData } = useChannelsQuery();

const posts = computed(() => postsData.value ?? []);
const channels = computed(() => channelsData.value ?? []);

// Filters
const platformFilter = ref('all');
const filtered = computed(() =>
  platformFilter.value === 'all'
    ? posts.value
    : posts.value.filter((p) => (p.targets || []).some((t) => t.platform === platformFilter.value)),
);
const activePlatforms = computed(() => [...new Set(channels.value.map((c) => c.platform))]);

// Composer modal
const showComposer = ref(false);
const editId = ref(null);
const composerDate = ref('');
function openNew(date = '') { editId.value = null; composerDate.value = date; showComposer.value = true; }
function openEdit(id) { editId.value = id; composerDate.value = ''; showComposer.value = true; }
function onSaved() { showComposer.value = false; refetch(); }

// Calendar
const today = new Date();
const cursor = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const monthLabel = computed(() => cursor.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
const shift = (d) => { cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + d, 1); };

const byDay = computed(() => {
  const map = {};
  for (const p of filtered.value) {
    if (!p.scheduled_at) continue;
    const d = new Date(p.scheduled_at);
    if (Number.isNaN(d.getTime())) continue;
    (map[`${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`] ||= []).push(p);
  }
  return map;
});
const weeks = computed(() => {
  const y = cursor.value.getFullYear(); const m = cursor.value.getMonth();
  const firstDow = new Date(y, m, 1).getDay();
  const days = new Date(y, m + 1, 0).getDate();
  const cells = [];
  for (let i = 0; i < firstDow; i++) cells.push(null);
  for (let d = 1; d <= days; d++) cells.push({ day: d, key: `${y}-${m}-${d}`, iso: `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}` });
  while (cells.length % 7) cells.push(null);
  const out = [];
  for (let i = 0; i < cells.length; i += 7) out.push(cells.slice(i, i + 7));
  return out;
});
const dows = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const needsAttention = computed(() =>
  posts.value.filter((p) => ['pending_approval', 'draft', 'failed'].includes(p.status)),
);
</script>

<template>
  <section class="stack">
    <header class="row" style="justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <div>
        <h2>Calendar</h2>
        <p class="muted">Plan &amp; publish across brands and channels.</p>
      </div>
      <div class="row">
        <button class="btn secondary" @click="router.push({ name: 'publishing-settings' })">⚙ Settings</button>
        <button class="btn" @click="openNew()">＋ New post</button>
      </div>
    </header>

    <!-- Filter + legend -->
    <div class="row" style="justify-content: space-between; flex-wrap: wrap; gap: 10px;">
      <div class="chips">
        <span class="chip" :class="{ on: platformFilter === 'all' }" @click="platformFilter = 'all'">All</span>
        <span
          v-for="pl in activePlatforms" :key="pl"
          class="chip" :class="{ on: platformFilter === pl }" @click="platformFilter = pl"
        >
          <span class="dot" :style="{ background: platformMeta(pl).color }" /> {{ platformMeta(pl).label }}
        </span>
      </div>
      <div class="row">
        <button class="btn secondary" @click="shift(-1)">‹</button>
        <strong style="min-width: 150px; text-align: center;">{{ monthLabel }}</strong>
        <button class="btn secondary" @click="shift(1)">›</button>
      </div>
    </div>

    <p v-if="isLoading" class="muted">Loading…</p>
    <div v-else class="cal card">
      <div v-for="d in dows" :key="d" class="dow">{{ d }}</div>
      <template v-for="(week, wi) in weeks" :key="wi">
        <div v-for="(cell, ci) in week" :key="`${wi}-${ci}`" class="cell" :class="{ blank: !cell }">
          <template v-if="cell">
            <div class="cell-head">
              <span class="num">{{ cell.day }}</span>
              <button class="add" @click="openNew(cell.iso)">＋</button>
            </div>
            <button
              v-for="p in (byDay[cell.key] || [])" :key="p.id"
              class="post" :style="{ borderLeftColor: statusMeta(p.status).color }"
              @click="openEdit(p.id)"
            >
              <span class="post-plats">
                <span v-for="t in p.targets" :key="t.id" class="pdot" :style="{ background: platformMeta(t.platform).color }">{{ platformMeta(t.platform).letter }}</span>
              </span>
              <span class="post-title">{{ p.title || p.caption || 'Untitled' }}</span>
            </button>
          </template>
        </div>
      </template>
    </div>

    <!-- Needs attention -->
    <div v-if="needsAttention.length" class="card stack">
      <strong>Needs attention</strong>
      <button
        v-for="p in needsAttention" :key="p.id"
        class="att row" @click="openEdit(p.id)"
      >
        <span class="badge" :style="{ background: statusMeta(p.status).color + '22', color: statusMeta(p.status).color }">
          {{ statusMeta(p.status).label }}
        </span>
        <span>{{ p.title || p.caption || 'Untitled' }}</span>
        <span class="post-plats" style="margin-left: auto;">
          <span v-for="t in p.targets" :key="t.id" class="pdot" :style="{ background: platformMeta(t.platform).color }">{{ platformMeta(t.platform).letter }}</span>
        </span>
      </button>
    </div>

    <PostComposer
      v-if="showComposer"
      :post-id="editId"
      :initial-date="composerDate"
      @close="showComposer = false"
      @saved="onSaved"
    />
  </section>
</template>

<style scoped>
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); background: var(--surface); padding: 5px 11px; border-radius: 999px; font-size: 13px; cursor: pointer; }
.chip.on { border-color: var(--primary); background: #eef2f8; color: var(--primary); font-weight: 600; }
.dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: var(--border); padding: 1px; }
.dow { background: var(--surface); padding: 8px; font-size: 12px; color: var(--muted); text-align: center; font-weight: 600; }
.cell { background: var(--surface); min-height: 110px; padding: 6px; display: flex; flex-direction: column; gap: 4px; }
.cell.blank { background: var(--bg); }
.cell-head { display: flex; justify-content: space-between; align-items: center; }
.num { font-size: 12px; color: var(--muted); }
.add { border: none; background: transparent; color: var(--primary); font-size: 14px; opacity: 0; }
.cell:hover .add { opacity: 1; }
.post { text-align: left; border: none; border-left: 3px solid var(--primary); background: var(--bg); border-radius: 6px; padding: 4px 6px; display: flex; flex-direction: column; gap: 3px; cursor: pointer; }
.post-title { font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.post-plats { display: inline-flex; gap: 3px; }
.pdot { width: 16px; height: 16px; border-radius: 4px; color: #fff; font-size: 9px; font-weight: 700; display: grid; place-items: center; }
.att { width: 100%; text-align: left; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 9px 12px; cursor: pointer; gap: 10px; }
</style>
