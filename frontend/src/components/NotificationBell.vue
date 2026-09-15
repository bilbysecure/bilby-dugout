<script setup>
import { computed } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { NotificationsApi } from '@/api/endpoints';

// Poll periodically for unread count (simple; could be push later).
const { data } = useQuery({
  queryKey: ['notifications'],
  queryFn: () => NotificationsApi.list(),
  refetchInterval: 60000,
});

const unread = computed(() => (data.value ?? []).filter((n) => !n.is_read).length);
</script>

<template>
  <router-link :to="{ name: 'notifications' }" class="bell" title="Notifications">
    <span>🔔</span>
    <span v-if="unread" class="count">{{ unread > 9 ? '9+' : unread }}</span>
  </router-link>
</template>

<style scoped>
.bell { position: relative; display: inline-flex; align-items: center; font-size: 18px; }
.count {
  position: absolute; top: -6px; right: -10px;
  background: var(--danger); color: #fff; font-size: 11px; line-height: 1;
  padding: 2px 5px; border-radius: 999px; font-weight: 700;
}
</style>
