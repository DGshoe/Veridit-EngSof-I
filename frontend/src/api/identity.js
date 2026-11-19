// Identity Service endpoints (backend: Veridit Identity, /api/v1).
// Full contract: <API>/api/documentation (Swagger).
import { authRequest, request, tokenStore } from './client';

/** REQ01 — creates the account (does NOT log in). Returns the user. */
export async function register(payload) {
  const res = await request('POST', '/auth/register', payload);
  return res.data;
}

/** REQ03 — stores the access token and returns the user. */
export async function login(email, password) {
  const res = await request('POST', '/auth/login', { email, password });
  tokenStore.set(res.data.access_token);
  return res.data.user;
}

/** REQ04 — invalidates the token on the server and forgets it locally. */
export async function logout() {
  try {
    if (tokenStore.get()) await authRequest('POST', '/auth/logout');
  } finally {
    tokenStore.clear();
  }
}

/** Logged-in user (masked CPF). */
export async function me() {
  const res = await authRequest('GET', '/me');
  return res.data;
}

/** REQ02 — always answers with the same message (does not reveal accounts). */
export async function forgotPassword(email) {
  const res = await request('POST', '/auth/forgot-password', { email });
  return res.message;
}

/** REQ02 — new password using the token from the e-mail link. */
export async function resetPassword({ token, email, password, password_confirmation }) {
  const res = await request('POST', '/auth/reset-password', { token, email, password, password_confirmation });
  return res.message;
}

export const hasToken = () => Boolean(tokenStore.get());
