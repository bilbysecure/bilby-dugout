<script setup>
// BilbyPixel logomark — the official PNG export (public/bilby-logomark.png).
// The export is centered with ~14% transparent margin, so we scale it up
// inside a clipped box to make the mark fill the tile like an app icon.
defineProps({ size: { type: [Number, String], default: 38 } });
</script>

<template>
  <span class="bilby-logo" :style="{ width: `${size}px`, height: `${size}px`, borderRadius: `${Number(size) * 0.24}px` }">
    <img src="/bilby-logomark.png" alt="BilbyDugout" />
  </span>
</template>

<style scoped>
.bilby-logo { display: inline-block; overflow: hidden; flex: none; }
.bilby-logo img { width: 100%; height: 100%; object-fit: contain; transform: scale(1.34); }
</style>
