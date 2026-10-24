// Talks to the HOTELGO JSON API (PHP, /api). The access token lives in memory only; the refresh token in sessionStorage.
const ROOT = location.pathname.replace(/\/panel(\/.*)?$/, '');
const BASE = ROOT + '/api';
const RK = 'hg_refresh';
let access = null;
let refreshing = null;

export const store = {
  get refresh() { try { return sessionStorage.getItem(RK); } catch { return null; } },
  set refresh(v) { try { v ? sessionStorage.setItem(RK, v) : sessionStorage.removeItem(RK); } catch { /* private mode */ } },
};

export class ApiError extends Error {
  constructor(message, status) { super(message); this.status = status; }
}

function send(method, path, body, token) {
  const headers = {};
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = 'Bearer ' + token;
  return fetch(BASE + path, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
}

async function readJson(res) {
  const text = await res.text();
  try { return text ? JSON.parse(text) : {}; } catch { return { error: 'The server sent an unexpected answer.' }; }
}

async function renew() {
  if (!store.refresh) return false;
  if (!refreshing) {
    refreshing = (async () => {
      const res = await send('POST', '/auth/refresh', { refresh_token: store.refresh });
      if (!res.ok) { access = null; store.refresh = null; return false; }
      const j = await readJson(res);
      access = j.tokens.access_token; store.refresh = j.tokens.refresh_token;
      return true;
    })().finally(() => { refreshing = null; });
  }
  return refreshing;
}

export async function api(method, path, body) {
  let res = await send(method, path, body, access);
  if (res.status === 401 && (await renew())) res = await send(method, path, body, access);
  const j = await readJson(res);
  if (!res.ok) throw new ApiError(j.error || j.message || 'Something went wrong.', res.status);
  return j;
}

export async function signIn(email, password) {
  const res = await send('POST', '/auth/login', { email, password });
  const j = await readJson(res);
  if (!res.ok) throw new ApiError(j.error || 'Could not sign in.', res.status);
  access = j.tokens.access_token; store.refresh = j.tokens.refresh_token;
  return j.user;
}

export async function restore() {
  if (!store.refresh || !(await renew())) return null;
  try { return (await api('GET', '/me')).user; } catch { return null; }
}

export async function signOut() {
  const t = store.refresh;
  access = null; store.refresh = null;
  if (t) { try { await send('POST', '/auth/logout', { refresh_token: t }); } catch { /* offline: token expires by itself */ } }
}
