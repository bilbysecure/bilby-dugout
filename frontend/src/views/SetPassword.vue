<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { OnboardingApi } from '@/api/endpoints';
import { useAuthStore } from '@/stores/auth';
import BilbyLogo from '@/components/BilbyLogo.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const token = computed(() => String(route.query.token || ''));
const password = ref('');
const confirm = ref('');
const busy = ref(false);
const error = ref('');

const canSubmit = computed(() => password.value.length >= 8 && password.value === confirm.value && token.value);

async function submit() {
  error.value = '';
  if (password.value !== confirm.value) { error.value = 'Passwords do not match.'; return; }
  busy.value = true;
  try {
    const data = await OnboardingApi.accept(token.value, password.value);
    auth.setSession(data.access_token, data.user);
    router.replace({ name: 'dashboard' });
  } catch (e) {
    error.value = e?.response?.data?.error?.message || 'This invite link is invalid or has expired.';
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <div class="wrap">
    <div class="card">
      <div class="brand"><BilbyLogo :size="40" /><div><h1>BilbyDugout</h1><p class="muted">Set your password</p></div></div>

      <template v-if="!token">
        <p class="err">Missing invite token. Please use the link from your invitation email.</p>
      </template>
      <template v-else>
        <label class="stack"><span>New password</span><input v-model="password" type="password" class="input" placeholder="At least 8 characters" /></label>
        <label class="stack"><span>Confirm password</span><input v-model="confirm" type="password" class="input" @keyup.enter="canSubmit && submit()" /></label>
        <p v-if="error" class="err">{{ error }}</p>
        <button class="btn" :disabled="!canSubmit || busy" @click="submit">{{ busy ? 'Setting…' : 'Set password & continue' }}</button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.wrap { min-height: 100vh; display: grid; place-items: center; padding: 20px; }
.card { width: min(420px, 96vw); display: flex; flex-direction: column; gap: 14px; }
.brand { display: flex; align-items: center; gap: 12px; margin-bottom: 4px; }
.brand h1 { margin: 0; font-size: 18px; }
.brand p { margin: 0; font-size: 13px; }
.stack { display: flex; flex-direction: column; gap: 6px; }
.stack span { font-size: 13px; color: var(--muted); }
.err { color: var(--danger); font-size: 14px; margin: 0; }
</style>
