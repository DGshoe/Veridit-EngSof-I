// HTTP client for the Veridit APIs.
// - Sends/receives JSON
// - Adds "Authorization: Bearer <token>" on authenticated calls
// - On 401, tries ONE token refresh (/auth/refresh) and repeats the call
// - Normalizes errors into ApiError { status, message, errors }

export const API_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1').replace(/\/$/, '');
// Commerce Service (packages, orders, payments, wallet). Accepts the same Identity JWT.
export const COMMERCE_URL = (import.meta.env.VITE_COMMERCE_API_URL || 'http://localhost:8001/api/v1').replace(/\/$/, '');

const TOKEN_KEY = 'veridit.access_token';

export const tokenStore = {
  get: () => {
    try { return localStorage.getItem(TOKEN_KEY); } catch { return null; }
  },
  set: (token) => {
    try { localStorage.setItem(TOKEN_KEY, token); } catch { /* storage blocked */ }
  },
  clear: () => {
    try { localStorage.removeItem(TOKEN_KEY); } catch { /* storage blocked */ }
  },
};

export class ApiError extends Error {
  constructor(status, message, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors; // { field: ["mensagem", ...] } on 422
  }
}

// Fired when the session ends (token invalid and not refreshable).
export const SESSION_EXPIRED_EVENT = 'veridit:session-expired';

async function send(method, path, { body, token, base = API_URL, headers: extraHeaders = {} } = {}) {
  const headers = { Accept: 'application/json', ...extraHeaders };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  let response;
  try {
    response = await fetch(`${base}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiError(0, 'Não foi possível conectar ao servidor. Verifique se a API está no ar.');
  }

  if (response.status === 204) return null;

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new ApiError(response.status, data.message || `Erro ${response.status}`, data.errors || {});
  }

  return data;
}

let refreshing = null; // shares one refresh between concurrent 401s

async function refreshToken() {
  const current = tokenStore.get();
  if (!current) return null;

  refreshing ??= send('POST', '/auth/refresh', { token: current })
    .then((res) => {
      tokenStore.set(res.data.access_token);
      return res.data.access_token;
    })
    .catch(() => null)
    .finally(() => { refreshing = null; });

  return refreshing;
}

/**
 * Public call (no token).
 * options.base: API base URL (default: Identity); options.headers: extra headers.
 */
export function request(method, path, body, options = {}) {
  return send(method, path, { body, ...options });
}

/** Authenticated call: Bearer token + automatic refresh on 401 (the token is always renewed at the Identity). */
export async function authRequest(method, path, body, options = {}) {
  try {
    return await send(method, path, { body, ...options, token: tokenStore.get() });
  } catch (error) {
    if (error.status !== 401) throw error;

    const newToken = await refreshToken();
    if (!newToken) {
      tokenStore.clear();
      window.dispatchEvent(new Event(SESSION_EXPIRED_EVENT));
      throw error;
    }
    return send(method, path, { body, ...options, token: newToken });
  }
}
