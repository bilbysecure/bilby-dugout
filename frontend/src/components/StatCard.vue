<script setup>
defineProps({ label: String, value: [String, Number] });
</script>

<template>
  <div class="card stat">
    <div class="value">{{ value }}</div>
    <div class="muted">{{ label }}</div>
  </div>
</template>

<style scoped>
.stat { min-width: 130px; }
.value { font-size: 26px; font-weight: 700; color: var(--primary); }
</style>
