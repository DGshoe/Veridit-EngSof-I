// Commerce Service endpoints (backend: Veridit Commerce, /api/v1).
// Full contract: http://localhost:8001/api/documentation (Swagger).
import { authRequest, COMMERCE_URL, request } from './client';

const commerce = { base: COMMERCE_URL };

/** Fired whenever the balance may have changed (e.g. a purchase was confirmed). */
export const WALLET_CHANGED_EVENT = 'veridit:wallet-changed';
export const notifyWalletChanged = () => window.dispatchEvent(new Event(WALLET_CHANGED_EVENT));

/** D2 catalog: [{ code, name, credits, price_cents, price, price_per_credit }] */
export async function listPackages() {
  const res = await request('GET', '/packages', undefined, commerce);
  return res.data;
}

/** Payment methods enabled in this environment: [{ code, name, description }] */
export async function listPaymentMethods() {
  const res = await request('GET', '/payment-methods', undefined, commerce);
  return res.data;
}

/** Logged user's credits. */
export async function getBalance() {
  const res = await authRequest('GET', '/wallet', undefined, commerce);
  return res.data.balance;
}

/**
 * REQ05 — creates the order and opens the charge (PIX QR Code, Mercado Pago link...).
 * idempotencyKey: same key = same order (protects against double click / retry).
 */
export async function createOrder({ packageCode, paymentMethod, idempotencyKey }) {
  const res = await authRequest('POST', '/orders', { package: packageCode, payment_method: paymentMethod }, {
    ...commerce,
    headers: { 'Idempotency-Key': idempotencyKey },
  });
  return res.data;
}

/** Order with its payment data and current status (pendente | pago | falhou). */
export async function getOrder(id) {
  const res = await authRequest('GET', `/orders/${encodeURIComponent(id)}`, undefined, commerce);
  return res.data;
}

/** Test environments only: simulate the provider's answer (aprovado | recusado). */
export async function simulatePayment(id, outcome) {
  const res = await authRequest('POST', `/orders/${encodeURIComponent(id)}/simulate`, { outcome }, commerce);
  return res.data;
}
