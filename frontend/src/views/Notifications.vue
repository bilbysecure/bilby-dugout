<script setup>
import { computed } from 'vue';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { NotificationsApi } from '@/api/endpoints';

const qc = useQueryClient();
const { data, isLoading } = useQuery({ queryKey: ['notifications'], queryFn: () => NotificationsApi.list() });
const items = computed(() => data.value ?? []);

const invalidate = () => qc.invalidateQueries({ queryKey: ['notifications'] });
const readMut = useMutation({ mutationFn: (id) => NotificationsApi.markRead(id), onSuccess: invalidate });
const readAllMut = useMutation({ mutationFn: () => NotificationsApi.markAllRead(), onSuccess: invalidate });
</script>

<template>
  <section class="stack">
    <header class="row" style="justify-content: space-between;">
      <div>
        <h2>Notifications</h2>
        <p class="muted">Status changes and mentions for you.</p>
      </div>
      <button class="btn secondary" @click="readAllMut.mutate()">Mark all read</button>
    </header>

    <p v-if="isLoading" class="muted">Loading…</p>
    <p v-else-if="!items.length" class="muted">You're all caught up.</p>
    <div v-else class="stack">
      <div v-for="n in items" :key="n.id" class="card row" :class="{ unread: !n.is_read }" style="justify-content: space-between;">
        <div>
          <strong>{{ n.title }}</strong>
          <p class="muted" style="margin: 4px 0 0;">{{ n.message }}</p>
        </div>
        <button v-if="!n.is_read" class="btn secondary" @click="readMut.mutate(n.id)">Mark read</button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.unread { border-left: 3px solid var(--primary); }
</style>
