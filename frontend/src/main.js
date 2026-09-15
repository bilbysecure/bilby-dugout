import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';

import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/auth';
import './styles.css';

async function bootstrap() {
  const app = createApp(App);

  app.use(createPinia());
  app.use(VueQueryPlugin);

  // Restore the session (silent refresh) BEFORE the router runs its guard.
  const auth = useAuthStore();
  await auth.init();

  app.use(router);
  app.mount('#app');
}

bootstrap();
