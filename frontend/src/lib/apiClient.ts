import axios, { AxiosError } from 'axios';
import type { AxiosRequestConfig } from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

const getTokens = () => ({
  access: sessionStorage.getItem('crm.access'),
  refresh: sessionStorage.getItem('crm.refresh'),
});

let refreshing: Promise<string | null> | null = null;
function refreshAccess(): Promise<string | null> {
  if (!refreshing) {
    refreshing = api
      .post('/auth/refresh', {}, { headers: { Authorization: `Bearer ${getTokens().refresh}` } })
      .then((r) => {
        sessionStorage.setItem('crm.access', r.data.data.access_token);
        return r.data.data.access_token as string;
      })
      .catch(() => {
        sessionStorage.clear();
        if (!location.pathname.startsWith('/login')) location.href = '/login?expired=1';
        return null;
      })
      .finally(() => {
        refreshing = null;
      });
  }
  return refreshing;
}

api.interceptors.request.use((cfg) => {
  const { access } = getTokens();
  if (access) cfg.headers.Authorization = `Bearer ${access}`;
  return cfg;
});

api.interceptors.response.use(
  (r) => r,
  async (err: AxiosError<{ code?: string }>) => {
    const orig = err.config as AxiosRequestConfig & { _retried?: boolean };
    const status = err.response?.status;
    const code = err.response?.data?.code;
    // v2 session timeout (specs/16): backend sends 401 {code:'session_expired'}
    if (status === 401 && (code === 'session_expired' || import.meta.env.VITE_SESSION_TIMEOUT_ENABLED === 'true')) {
      if (code === 'session_expired') {
        sessionStorage.clear();
        location.href = '/login?expired=1';
        return Promise.reject(err);
      }
    }
    if (status === 401 && !orig._retried && getTokens().refresh) {
      orig._retried = true;
      const token = await refreshAccess();
      if (token && orig.headers) {
        return api({ ...orig, headers: { ...orig.headers, Authorization: `Bearer ${token}` } });
      }
    }
    return Promise.reject(err);
  },
);

export default api;
