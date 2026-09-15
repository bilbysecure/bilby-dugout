<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import BilbyLogo from '@/components/BilbyLogo.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const username = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

// Local-only quick sign-in (set VITE_DEV_LOGIN=true). Mirrors the seeded users.
const devMode = import.meta.env.VITE_DEV_LOGIN === 'true';
const devUsers = [
  { email: 'admin@bilbypixel.com', label: 'Global Admin' },
  { email: 'designer@bilbypixel.com', label: 'Agency Staff' },
  { email: 'owner@acme.com', label: 'Client Owner' },
  { email: 'member@acme.com', label: 'Client Member' },
];

async function go(fn) {
  error.value = '';
  loading.value = true;
  try {
    await fn();
    router.replace(route.query.redirect || { name: 'dashboard' });
  } catch (e) {
    error.value = e?.response?.data?.error?.message || 'Sign in failed. Check your credentials.';
  } finally {
    loading.value = false;
  }
}

const submit = () => go(() => auth.login(username.value, password.value));
const devSignIn = (email) => go(() => auth.devLogin(email));
</script>

<template>
  <div class="login-wrap">
    <div class="card login-card">
      <!-- Hero panel -->
      <div class="hero">
        <BilbyLogo :size="52" />
        <h1>Welcome to<br />BilbyDugout</h1>
        <p>Your client request portal — intake, review, approvals, publishing and reporting, all in one calm workspace.</p>
        <div class="crys"><span></span><span></span><span></span></div>
      </div>

      <!-- Form panel -->
      <form class="form" @submit.prevent="submit">
        <h2>Sign in</h2>
        <p class="lead muted">Use your BilbyPixel account.</p>

        <label class="stack">
          <span>Email</span>
          <input v-model="username" class="input" type="email" autocomplete="username" required />
        </label>

        <label class="stack">
          <span>Password</span>
          <input v-model="password" class="input" type="password" autocomplete="current-password" required />
        </label>

        <p v-if="error" class="error">{{ error }}</p>

        <button class="btn" type="submit" :disabled="loading">
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>

        <p class="muted sso-note">Single sign-on via Azure AD (handled server-side).</p>

        <div v-if="devMode" class="dev">
          <span class="muted">Dev sign-in (local):</span>
          <div class="dev-buttons">
            <button
              v-for="u in devUsers" :key="u.email" type="button"
              class="btn secondary dev-btn" :disabled="loading"
              @click="devSignIn(u.email)"
            >{{ u.label }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-card { width: 860px; max-width: 96vw; padding: 0; display: grid; grid-template-columns: 1.05fr 0.95fr; overflow: hidden; border-radius: 24px; }

/* Hero */
.hero { padding: 40px 38px; position: relative; overflow: hidden;
  background: linear-gradient(150deg, rgba(108, 74, 182, 0.16), rgba(95, 182, 196, 0.13)); }
.hero::after { content: ""; position: absolute; width: 260px; height: 260px; right: -70px; bottom: -90px; border-radius: 50%;
  background: radial-gradient(circle, rgba(138, 111, 209, 0.45), transparent 65%); }
.hero h1 { margin: 22px 0 10px; font-size: 28px; line-height: 1.15; letter-spacing: -0.4px; color: var(--text); }
.hero p { color: #54506B; font-size: 14px; max-width: 320px; line-height: 1.6; margin: 0; }
.crys { display: flex; gap: 10px; margin-top: 26px; }
.crys span { flex: 1; height: 60px; border-radius: 14px; border: 1px solid var(--ring); backdrop-filter: blur(8px); }
.crys span:nth-child(1) { background: linear-gradient(160deg, rgba(108, 74, 182, 0.28), rgba(255, 255, 255, 0.2)); }
.crys span:nth-child(2) { background: linear-gradient(160deg, rgba(95, 182, 196, 0.28), rgba(255, 255, 255, 0.2)); }
.crys span:nth-child(3) { background: linear-gradient(160deg, rgba(124, 147, 232, 0.28), rgba(255, 255, 255, 0.2)); }

/* Form */
.form { padding: 40px 38px; display: flex; flex-direction: column; gap: 14px; justify-content: center; background: rgba(255, 255, 255, 0.35); }
.form h2 { margin: 0; font-size: 20px; }
.lead { margin: -6px 0 4px; font-size: 13px; }
label span { font-size: 12.5px; color: var(--muted); font-weight: 600; }
.error { color: var(--danger); margin: 0; font-size: 14px; }
.sso-note { font-size: 12px; text-align: center; margin: 0; }
.dev { border-top: 1px solid var(--line); padding-top: 12px; display: flex; flex-direction: column; gap: 8px; }
.dev span { font-size: 12px; }
.dev-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.dev-btn { font-size: 13px; padding: 8px; }
@media (max-width: 680px) { .login-card { grid-template-columns: 1fr; } .hero { display: none; } }
</style>
