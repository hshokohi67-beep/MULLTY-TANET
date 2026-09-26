'use server';

import { api, ApiError } from '@/lib/api';
import type { BrowserSubscription } from '@/lib/push-client';
import { assertTenant } from '@/lib/storefront';

/** Browser notifications. The API checks the tracking token / staff permission and the push service. */

const ULID = /^[0-9A-HJKMNP-TV-Z]{26}$/i;
type Result = { ok: true } | { ok: false; message: string };

function fail(error: unknown): { ok: false; message: string } {
  if (error instanceof ApiError) return { ok: false, message: error.message };
  throw error;
}

function clean(sub: BrowserSubscription): BrowserSubscription {
  return { endpoint: String(sub.endpoint).slice(0, 1000), keys: { p256dh: String(sub.keys?.p256dh ?? '').slice(0, 120), auth: String(sub.keys?.auth ?? '').slice(0, 40) } };
}

/** The platform's public key (null while push is off). */
export async function pushKey(): Promise<string | null> {
  try {
    return (await api<{ data: { public_key: string | null } }>('/public/push/key', { auth: false, tenant: false, revalidate: 300 })).data.public_key;
  } catch {
    return null;
  }
}

/** A customer following their order; `url` is the tracking page to open when tapped. */
export async function followOrder(tenant: string, orderId: string, trackingToken: string, sub: BrowserSubscription, url: string): Promise<Result> {
  assertTenant(tenant);
  if (!ULID.test(orderId) || trackingToken.length > 200) return { ok: false, message: 'سفارش نامعتبر است.' };
  try {
    await api(`/public/orders/${orderId}/push`, {
      method: 'POST', auth: false, tenant, headers: { 'X-Order-Token': trackingToken },
      body: { ...clean(sub), url: url.startsWith('/') && !url.startsWith('//') ? url.slice(0, 400) : null },
    });

    return { ok: true };
  } catch (error) {
    return fail(error);
  }
}

export async function staffPushStatus(endpoint: string): Promise<boolean> {
  try {
    return (await api<{ data: { subscribed: boolean } }>('/push/subscription/status', { method: 'POST', body: { endpoint: endpoint.slice(0, 1000) } })).data.subscribed;
  } catch {
    return false;
  }
}

export async function staffSubscribe(sub: BrowserSubscription): Promise<Result> {
  try {
    await api('/push/subscription', { method: 'POST', body: clean(sub) });

    return { ok: true };
  } catch (error) {
    return fail(error);
  }
}

export async function staffUnsubscribe(endpoint: string): Promise<Result> {
  try {
    await api('/push/subscription/remove', { method: 'POST', body: { endpoint: endpoint.slice(0, 1000) } });

    return { ok: true };
  } catch (error) {
    return fail(error);
  }
}
