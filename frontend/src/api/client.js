import axios from 'axios';

/**
 * Axios instance for the SlimPHP API.
 * - access token (in memory) attached as Bearer on every request
 * - withCredentials so the httpOnly refresh cookie is sent to /auth/refresh
 * - a 401 triggers a single silent refresh + retry, else hard logout
 */
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || 'http://localhost:8080/api/v1',
  withCredentials: true,
});

// These are wired by the auth store at startup to avoid a circular import.
let getToken = () => null;
let onRefresh = async () => null; // returns new access token or null
let onAuthLost = () => {};
let getImpersonation = () => null; // returns a client_email to "view as", or null

export function configureApi({ token, refresh, authLost, impersonate }) {
  getToken = token;
  onRefresh = refresh;
  onAuthLost = authLost;
  if (impersonate) getImpersonation = impersonate;
}

api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  const imp = getImpersonation();
  if (imp) {
    config.headers['X-Impersonate-Client'] = imp;
  }
  return config;
});

let refreshing = null;

api.interceptors.response.use(
  (res) => res,
  async (error) => {
    const { response, config } = error;
    const isAuthCall = config?.url?.includes('/auth/');

    if (response?.status === 401 && !config.__retried && !isAuthCall) {
      config.__retried = true;
      try {
        // de-duplicate concurrent refreshes
        refreshing = refreshing || onRefresh();
        const newToken = await refreshing;
        refreshing = null;
        if (newToken) {
          config.headers.Authorization = `Bearer ${newToken}`;
          return api(config);
        }
      } catch {
        refreshing = null;
      }
      onAuthLost();
    }
    return Promise.reject(error);
  },
);

export default api;
