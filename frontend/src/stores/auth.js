import { defineStore } from 'pinia';
import { AuthApi } from '@/api/endpoints';
import { configureApi } from '@/api/client';

/**
 * Auth state. The access token lives only in memory (decision #5);
 * the refresh token is an httpOnly cookie handled by the browser/API.
 */
export const useAuthStore = defineStore('auth', {
  state: () => ({
    accessToken: null,
    user: null,        // { id, email, name, role, designation, client_email, permissions }
    ready: false,      // initial session probe finished
    impersonation: null, // { email, label } when "viewing as" a client
  }),

  getters: {
    isAuthenticated() { return !!this.accessToken && !!this.user; },
    impersonating() { return !!this.impersonation; },
    // Effective role — swaps to a client while "viewing as" a client (full client UI).
    role() { return this.impersonation ? 'client_owner' : (this.user?.role ?? null); },
    realRole() { return this.user?.role ?? null; },
    isAgency() { return ['global_admin', 'agency_staff'].includes(this.role); },
    isManager() { return !this.impersonation && (this.user?.role === 'global_admin' || this.user?.designation === 'operations_manager'); },
    isClient() { return ['client_owner', 'client_member'].includes(this.role); },
    // Matches RequestPolicy::create — agency staff create on behalf of a client; clients for their own company.
    canCreateRequests() { return this.isAgency || this.isClient; },
    can() { return (perm) => (!this.impersonation && this.user?.role === 'global_admin') || (this.user?.permissions ?? []).includes(perm); },
  },

  actions: {
    /** Wire the axios interceptors to this store, then try to restore a session. */
    async init() {
      configureApi({
        token: () => this.accessToken,
        refresh: () => this.tryRefresh(),
        authLost: () => this.clear(),
        impersonate: () => this.impersonation?.email || null,
      });
      // Attempt a silent refresh using the httpOnly cookie (survives reload).
      await this.tryRefresh();
      if (this.accessToken && !this.user) {
        try {
          this.user = await AuthApi.me();
        } catch {
          this.clear();
        }
      }
      this.ready = true;
    },

    async login(username, password) {
      const data = await AuthApi.login(username, password);
      this.accessToken = data.access_token;
      this.user = data.user;
      return this.user;
    },

    /** DEV ONLY — sign in by email via the local bypass endpoint. */
    async devLogin(email) {
      const data = await AuthApi.devLogin(email);
      this.accessToken = data.access_token;
      this.user = data.user;
      return this.user;
    },

    async tryRefresh() {
      try {
        const data = await AuthApi.refresh();
        this.accessToken = data.access_token;
        return this.accessToken;
      } catch {
        return null;
      }
    },

    async logout() {
      try {
        await AuthApi.logout();
      } finally {
        this.clear();
      }
    },

    /** Apply a session issued outside the login form (e.g. invite set-password). */
    setSession(accessToken, user) {
      this.accessToken = accessToken;
      this.user = user;
    },

    impersonateClient(email, label) { this.impersonation = { email, label }; },
    stopImpersonating() { this.impersonation = null; },

    clear() {
      this.accessToken = null;
      this.user = null;
      this.impersonation = null;
    },
  },
});
