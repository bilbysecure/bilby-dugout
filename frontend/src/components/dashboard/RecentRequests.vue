<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { typeMeta, statusMeta, priorityMeta, fmtDate } from '@/constants/requestMeta';

const props = defineProps({ requests: { type: Array, default: () => [] }, limit: { type: Number, default: 6 } });
const router = useRouter();
const items = computed(() => props.requests.slice(0, props.limit));
const open = (r) => router.push({ name: 'request-detail', params: { id: r.id } });
</script>

<template>
  <div class="stack">
    <div class="row" style="justify-content: space-between; align-items: center;">
      <h3 style="margin: 0;">Recent Requests</h3>
      <button class="link" @click="router.push({ name: 'requests' })">View all</button>
    </div>

    <p v-if="!items.length" class="muted">No requests yet.</p>
    <div v-for="r in items" :key="r.id" class="req card" @click="open(r)">
      <div class="ic" :style="{ background: typeMeta(r.type).tint }">{{ typeMeta(r.type).icon }}</div>
      <div class="body">
        <strong>{{ r.title }}</strong>
        <p class="desc muted">{{ r.description || 'No description.' }}</p>
        <div class="pills">
          <span class="pill" :style="{ color: statusMeta(r.status)[1], background: statusMeta(r.status)[2] }">{{ statusMeta(r.status)[0] }}</span>
          <span class="pill" :style="{ color: priorityMeta(r.priority)[1], background: priorityMeta(r.priority)[2] }">{{ priorityMeta(r.priority)[0] }}</span>
          <span v-if="r.due_date" class="pill due">📅 {{ fmtDate(r.due_date) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.req { display: flex; gap: 12px; cursor: pointer; transition: box-shadow .15s, border-color .15s; }
.req:hover { border-color: var(--primary); box-shadow: 0 2px 10px rgba(46,84,150,.08); }
.ic { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; font-size: 18px; flex: none; }
.body { min-width: 0; }
.body strong { font-size: 15px; }
.desc { margin: 2px 0 8px; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pills { display: flex; gap: 8px; flex-wrap: wrap; }
.pill { font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; }
.pill.due { color: var(--muted); background: #f2f4f7; font-weight: 500; }
.link { border: none; background: none; color: var(--primary); cursor: pointer; font-size: 14px; }
</style>
