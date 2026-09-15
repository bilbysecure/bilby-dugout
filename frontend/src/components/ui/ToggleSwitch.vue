<script setup>
const props = defineProps({ modelValue: Boolean, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);
function toggle() { if (!props.disabled) emit('update:modelValue', !props.modelValue); }
</script>

<template>
  <button
    type="button" class="sw" :class="{ on: modelValue, disabled }"
    role="switch" :aria-checked="modelValue" @click="toggle"
  >
    <span class="knob" />
  </button>
</template>

<style scoped>
.sw { width: 46px; height: 26px; border-radius: 999px; border: none; background: #cbd2dc; position: relative; cursor: pointer; padding: 0; transition: background .2s; flex: none; }
.sw.on { background: #6366f1; }
.sw.disabled { opacity: .5; cursor: not-allowed; }
.knob { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.25); }
.sw.on .knob { transform: translateX(20px); }
</style>
